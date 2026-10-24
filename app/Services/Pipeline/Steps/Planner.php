<?php

namespace App\Services\Pipeline\Steps;

use App\Models\Category;
use App\Services\Llm\LlmClient;
use App\Services\Pipeline\BudgetParser;
use App\Services\Pipeline\PipelineContext;
use App\Services\Pipeline\Prompts;
use App\Services\Pipeline\Requirement;
use App\Services\Pipeline\RequirementCatalog;

/**
 * Planner (AI): turns the sentence into a work order: category, budget and
 * must-haves. Code then checks the AI's answer: unknown categories are
 * rejected, unknown must-haves become "unsupported", and a budget written
 * explicitly in the text always wins over the AI's reading.
 */
class Planner implements PipelineStep
{
    use UsesLlm;

    public function __construct(LlmClient $llm, private RequirementCatalog $catalog, private BudgetParser $budgets)
    {
        $this->llm = $llm;
    }

    public function handle(PipelineContext $context): StepOutcome
    {
        $query = $context->session->fullQuery();

        $answer = $this->ask($context, 'plan',
            Prompts::planner($this->catalog->categories(), $this->catalog->describeForPrompt()),
            "Shopper request: {$query}",
            ['query' => $query],
        );

        $slug = is_string($answer['category'] ?? null) ? $answer['category'] : null;
        if (! $this->catalog->exists($slug)) {
            $slug = null;
        }

        $parsedBudget = $this->budgets->parse($query);
        $aiBudget = is_numeric($answer['budget_inr'] ?? null) && $answer['budget_inr'] > 0 ? (float) $answer['budget_inr'] : null;
        $budget = $parsedBudget ?? $aiBudget;

        $requirements = [];
        if ($slug) {
            foreach ((array) ($answer['must_haves'] ?? []) as $item) {
                $key = is_array($item) ? ($item['key'] ?? null) : $item;
                if (! is_string($key)) {
                    continue;
                }
                $requirement = $this->catalog->make($slug, $key, is_array($item) ? ($item['value'] ?? null) : null)
                    ?? Requirement::unsupported(str_replace('_', ' ', $key));
                $requirements[$requirement->key] = $requirement;
            }
            foreach ((array) ($answer['unsupported'] ?? []) as $text) {
                if (is_string($text) && trim($text) !== '') {
                    $requirement = Requirement::unsupported($text);
                    $requirements[$requirement->key] = $requirement;
                }
            }
        }

        $context->category = $slug ? Category::firstWhere('slug', $slug) : null;
        if ($slug && ! $context->category) {
            $context->trace->note('planner', "The category \"{$slug}\" has no products loaded yet. Run php artisan products:import.", 'warning');
            $context->session->update(['status' => 'no_results']);

            return StepOutcome::Stop;
        }
        $context->originalBudget = $budget;
        $context->effectiveBudget = $budget;
        $context->requirements = array_values($requirements);
        $context->clarifyQuestion = $slug ? null : (is_string($answer['clarify_question'] ?? null) ? $answer['clarify_question'] : null);

        $budgetNote = match (true) {
            $budget === null => 'no budget given',
            $parsedBudget !== null && $aiBudget !== null && abs($parsedBudget - $aiBudget) > 0.5 => '₹'.number_format($budget).' (read from the text; the AI said ₹'.number_format($aiBudget).')',
            default => '₹'.number_format($budget),
        };
        $labels = array_map(fn (Requirement $r) => $r->label.($r->isSupported() ? '' : ' (can\'t check)'), $context->requirements);

        $context->trace->tool('planner',
            'Understand what the shopper wants before searching.',
            'plan(request)',
            sprintf('Category: %s. Budget: %s. Must-haves: %s.',
                $context->category?->name ?? ($slug ?? 'not stated'),
                $budgetNote,
                $labels ? implode(', ', $labels) : 'none'),
            data: ['work_order' => $context->workOrder()],
        );

        $context->session->update([
            'plan' => $context->workOrder(),
            'original_budget' => $budget,
            'effective_budget' => $budget,
        ]);

        return StepOutcome::Continue;
    }
}
