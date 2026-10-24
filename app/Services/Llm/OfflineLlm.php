<?php

namespace App\Services\Llm;

use App\Services\Pipeline\BudgetParser;
use App\Services\Pipeline\Requirement;

/**
 * Offline "AI": deterministic rules that answer the same tasks as Groq, using
 * the structured $context instead of the prompt. It keeps the app usable with
 * no API key, makes tests run without internet, and is the fallback when Groq
 * is down. It is clearly labelled as rule-based wherever its answers appear.
 */
class OfflineLlm implements LlmClient
{
    /** Numbers that set a must-have's value, e.g. "12gb ram" -> min_ram_gb 12. spec key => regex */
    private const PARAMS = [
        'ram_gb' => '/(\d+)\s*gb\s*(?:of\s*)?ram\b/',
        'storage_gb' => '/(\d+)\s*(gb|tb)\b(?!\s*(?:of\s*)?ram)/',
        'battery_mah' => '/(\d{4,5})\s*mah\b/',
        'rear_camera_mp' => '/(\d+)\s*mp\b/',
        'battery_hours' => '/(\d+)\s*(?:h|hr|hrs|hour|hours)\b/',
        'battery_days' => '/(\d+)\s*(?:day|days)\b/',
        'charging_w' => '/(\d+)\s*w\b/',
        'weight_kg' => '/(\d+(?:\.\d+)?)\s*kg\b/',
        'weight_g' => '/(\d+)\s*(?:g|gm|grams)\b/',
    ];

    public function __construct(private BudgetParser $budgets) {}

    public function name(): string
    {
        return 'Offline AI (rule-based)';
    }

    public function json(string $task, string $system, string $user, array $context = []): array
    {
        return match ($task) {
            'plan' => $this->plan((string) ($context['query'] ?? $user)),
            'replan_pick' => $this->replanPick($context['options'] ?? []),
            'explain' => ['claims' => $this->explain($context['products'] ?? [])],
            'rewrite' => ['claims' => $this->rewrite($context)],
            default => throw new LlmException("The offline AI does not know the task '{$task}'."),
        };
    }

    private function plan(string $query): array
    {
        $text = ' '.mb_strtolower($query).' ';

        $best = null;
        $bestScore = 0;
        foreach (config('shopmate_specs') as $slug => $category) {
            $score = 0;
            foreach ($category['keywords'] as $keyword) {
                if ($this->has($text, $keyword)) {
                    $score += mb_strlen($keyword);
                }
            }
            if ($score > $bestScore) {
                [$best, $bestScore] = [$slug, $score];
            }
        }

        $mustHaves = [];
        if ($best) {
            foreach (config("shopmate_specs.{$best}.requirements") as $key => $def) {
                $value = null;
                if (! empty($def['param']) && isset(self::PARAMS[$def['spec']]) && preg_match(self::PARAMS[$def['spec']], $text, $m)) {
                    $value = (float) $m[1] * (($m[2] ?? '') === 'tb' ? 1024 : 1);
                }
                $mentioned = collect($def['keywords'])->contains(fn ($k) => $this->has($text, $k));
                if ($value !== null || $mentioned) {
                    $mustHaves[] = ['key' => $key, 'value' => $value];
                }
            }
        }

        return [
            'category' => $best,
            'budget_inr' => $this->budgets->parse($query),
            'must_haves' => $mustHaves,
            'unsupported' => [],
            'clarify_question' => $best ? null : 'What kind of product are you looking for? For example earphones, a smartphone, a laptop, a smartwatch or running shoes.',
        ];
    }

    private function replanPick(array $options): array
    {
        usort($options, fn ($a, $b) => [$b['unlocks'], $a['type'] === 'raise_budget' ? 0 : 1] <=> [$a['unlocks'], $b['type'] === 'raise_budget' ? 0 : 1]);

        return [
            'option_id' => $options[0]['id'] ?? null,
            'reason' => 'Offline AI: picked the change that brings back the most products.',
        ];
    }

    /** Builds claims straight from the fact sheet, so they always pass verification. */
    private function explain(array $products): array
    {
        $claims = [];
        foreach ($products as $p) {
            $search = $p['steps']['search'];
            $rank = $p['steps']['rank'];
            $price = '₹'.number_format($p['price']);

            if ($p['budget'] !== null) {
                $claims[] = [
                    'product_id' => $p['product_id'],
                    'text' => $p['within_budget']
                        ? "Costs {$price} at {$p['store']}, within your ₹".number_format($p['budget']).' budget.'
                        : "Costs {$price} at {$p['store']}, above your original ₹".number_format($p['budget']).' budget.',
                    'cites' => $search,
                    'facts' => [
                        ['field' => 'price', 'value' => $p['price']],
                        ['field' => 'budget', 'value' => $p['budget']],
                        ['field' => 'within_budget', 'value' => $p['within_budget']],
                    ],
                ];
            } else {
                $claims[] = [
                    'product_id' => $p['product_id'],
                    'text' => "Lowest price found is {$price} at {$p['store']}.",
                    'cites' => $search,
                    'facts' => [['field' => 'price', 'value' => $p['price']]],
                ];
            }

            if ($p['rating'] !== null) {
                $claims[] = [
                    'product_id' => $p['product_id'],
                    'text' => sprintf('Ranked #%d: rated %s★ from %s reviews, weighted so ratings with few reviews count less.',
                        $p['rank'], $p['rating'], number_format($p['review_count'])),
                    'cites' => $rank,
                    'facts' => [
                        ['field' => 'rank', 'value' => $p['rank']],
                        ['field' => 'rating', 'value' => $p['rating']],
                        ['field' => 'review_count', 'value' => $p['review_count']],
                    ],
                ];
            }

            $confirmed = array_keys(array_filter($p['requirements'], fn ($s) => $s === Requirement::MET));
            if ($confirmed) {
                // Lower-case the first letter for mid-sentence use, but keep acronyms like "AMOLED" and "5G".
                $labels = array_map(fn ($k) => preg_match('/^[A-Z][a-z]/', $p['requirement_labels'][$k]) ? lcfirst($p['requirement_labels'][$k]) : $p['requirement_labels'][$k], $confirmed);
                $claims[] = [
                    'product_id' => $p['product_id'],
                    'text' => 'The listing confirms: '.implode(', ', $labels).'.',
                    'cites' => $search,
                    'facts' => array_map(fn ($k) => ['field' => "requirement.{$k}", 'value' => Requirement::MET], $confirmed),
                ];
            }
        }

        return $claims;
    }

    private function rewrite(array $context): array
    {
        $failedIds = array_unique(array_map(fn ($f) => (int) ($f['claim']['product_id'] ?? 0), $context['failed_claims'] ?? []));
        $products = array_filter($context['products'] ?? [], fn ($p) => in_array($p['product_id'], $failedIds, true));

        return array_slice($this->explain(array_values($products)), 0, count($context['failed_claims'] ?? []));
    }

    private function has(string $text, string $phrase): bool
    {
        return (bool) preg_match('/(?<![a-z0-9])'.preg_quote(mb_strtolower($phrase), '/').'(?![a-z0-9])/u', $text);
    }
}
