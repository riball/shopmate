<?php

namespace App\Services\Pipeline\Steps;

use App\Models\Claim;
use App\Services\Pipeline\FactSheet;
use App\Services\Pipeline\PipelineContext;
use App\Services\Pipeline\Requirement;

/**
 * Verify (code only): every claim is checked against the real data before the
 * shopper sees it:
 *   - it is about one of the picks and cites a trace step that covers that product
 *   - each listed fact (price, rating, review count, rank, specs, must-haves,
 *     "within budget") matches the database; "within budget" always uses the
 *     budget the shopper originally gave, even after a replan
 *   - the text contains no number that isn't one of those checked facts
 * Failed claims get ONE rewrite. Anything still wrong is deleted, and the trace says so.
 */
class Verifier implements PipelineStep
{
    public function __construct(private Explainer $explainer) {}

    public function handle(PipelineContext $context): StepOutcome
    {
        $sheet = new FactSheet($context);
        $checked = 0;
        $verified = [];
        $failed = [];

        foreach ($context->drafts as $claim) {
            $checked++;
            $reason = $this->check($claim, $sheet, $context);
            $this->store($context, $claim, $reason, 1);
            $reason === null ? $verified[] = $claim : $failed[] = ['claim' => $claim, 'reason' => $reason];
        }

        $removed = 0;
        if ($failed !== []) {
            $context->trace->note('verify', count($failed).' claim(s) failed the check, so the AI gets one rewrite. Reasons: '
                .implode('; ', array_unique(array_map(fn ($f) => $f['reason'], $failed))).'.', 'warning');

            $rewrites = $this->explainer->rewrite($context, $failed);
            $fixed = 0;
            foreach ($rewrites as $claim) {
                $checked++;
                $reason = $this->check($claim, $sheet, $context);
                $this->store($context, $claim, $reason, 2);
                if ($reason === null) {
                    $verified[] = $claim;
                    $fixed++;
                }
            }
            $removed = count($failed) - min($fixed, count($failed));
            if ($removed > 0) {
                $context->trace->note('verify', "{$removed} claim(s) were still wrong after the rewrite and were deleted. Only checked statements are shown.", 'warning');
            }
        }

        $context->claims = $verified;
        $context->trace->tool('verify',
            'Check every sentence against the real listing data before the shopper sees it.',
            'verify_claims(claims='.$checked.')',
            sprintf('%d of %d checks passed; %d claim(s) shown, %d removed.', count($verified), $checked, count($verified), $removed),
            array_values(array_unique(array_map(fn ($c) => $c['product_id'], $verified))),
        );

        $context->session->update(['claims_checked' => $checked, 'claims_removed' => $removed]);

        return StepOutcome::Continue;
    }

    /** @return string|null null when the claim is correct, otherwise why it failed */
    public function check(array $claim, FactSheet $sheet, PipelineContext $context): ?string
    {
        $truth = $sheet->for((int) $claim['product_id']);
        if ($truth === null) {
            return 'it is about a product that is not one of the picks';
        }
        if ($claim['text'] === '' || mb_strlen($claim['text']) > 300) {
            return 'the text is empty or too long';
        }
        if (! $claim['cites'] || ! $context->trace->exists($claim['cites'])) {
            return 'it cites a step that does not exist';
        }
        if (! $context->trace->stepCovers($claim['cites'], $truth['product_id'])) {
            return "step {$claim['cites']} did not produce facts about this product";
        }
        if ($claim['facts'] === []) {
            return 'it lists no checkable facts';
        }

        $allowedNumbers = $this->numbersIn($truth['name']);
        foreach ($claim['facts'] as $fact) {
            $field = is_array($fact) ? ($fact['field'] ?? null) : null;
            $value = is_array($fact) ? ($fact['value'] ?? null) : null;
            if (! is_string($field)) {
                return 'a fact has no field name';
            }
            $problem = $this->checkFact($field, $value, $truth);
            if ($problem !== null) {
                return $problem;
            }
            if (is_numeric($value)) {
                $allowedNumbers[] = (float) $value;
            } elseif (is_string($value)) {
                array_push($allowedNumbers, ...$this->numbersIn($value));
            }
            if (str_starts_with($field, 'requirement.')) {
                array_push($allowedNumbers, ...$this->numbersIn($truth['requirement_labels'][substr($field, 12)] ?? ''));
            }
            if (str_starts_with($field, 'spec.')) {
                array_push($allowedNumbers, ...$this->numbersIn($truth['spec_display'][substr($field, 5)] ?? ''));
            }
        }

        foreach ($this->numbersIn($claim['text']) as $number) {
            if (! $this->contains($allowedNumbers, $number)) {
                return 'the text mentions '.$this->format($number).', which is not one of its checked facts';
            }
        }

        return null;
    }

    private function checkFact(string $field, mixed $value, array $truth): ?string
    {
        if (str_starts_with($field, 'spec.')) {
            $key = substr($field, 5);
            if (! array_key_exists($key, $truth['specs'])) {
                return "the listing does not state \"{$key}\"";
            }

            return $this->same($truth['specs'][$key], $value) ? null : "{$key} is not ".json_encode($value);
        }

        if (str_starts_with($field, 'requirement.')) {
            $key = substr($field, 12);
            $status = $truth['requirements'][$key] ?? null;
            if ($status !== Requirement::MET) {
                return "\"{$key}\" is not confirmed by the listing";
            }

            return $value === null || $value === Requirement::MET || $value === true ? null : "\"{$key}\" can only be claimed as met";
        }

        return match ($field) {
            'price' => is_numeric($value) && abs($value - $truth['price']) < 0.5 ? null : 'the price is wrong',
            'budget' => $truth['budget'] !== null && is_numeric($value) && abs($value - $truth['budget']) < 0.5 ? null : 'the budget is wrong',
            'within_budget' => $truth['within_budget'] !== null && $this->bool($value) === $truth['within_budget'] ? null : '"within budget" is wrong for the original budget',
            'rating' => is_numeric($value) && $truth['rating'] !== null && abs($value - $truth['rating']) < 0.05 ? null : 'the rating is wrong',
            'review_count' => is_numeric($value) && (int) $value === $truth['review_count'] ? null : 'the review count is wrong',
            'rank' => is_numeric($value) && (int) $value === $truth['rank'] ? null : 'the rank is wrong',
            default => "\"{$field}\" is not a field that can be checked",
        };
    }

    private function same(mixed $truth, mixed $claimed): bool
    {
        if (is_bool($truth)) {
            return $this->bool($claimed) === $truth;
        }
        if (is_numeric($truth)) {
            return is_numeric($claimed) && abs((float) $claimed - (float) $truth) < 0.01;
        }

        return is_scalar($claimed) && mb_strtolower(trim((string) $claimed)) === mb_strtolower((string) $truth);
    }

    private function bool(mixed $value): ?bool
    {
        return match (true) {
            is_bool($value) => $value,
            in_array(mb_strtolower((string) $value), ['yes', 'true', '1'], true) => true,
            in_array(mb_strtolower((string) $value), ['no', 'false', '0'], true) => false,
            default => null,
        };
    }

    /** @return float[] every number in the text ("₹1,799" -> 1799, "4.3★" -> 4.3) */
    private function numbersIn(string $text): array
    {
        preg_match_all('/\d{1,3}(?:,\d{2,3})+(?:\.\d+)?|\d+(?:\.\d+)?/', $text, $m);

        return array_map(fn ($n) => (float) str_replace(',', '', $n), $m[0]);
    }

    private function contains(array $numbers, float $needle): bool
    {
        foreach ($numbers as $n) {
            if (abs($n - $needle) < 0.01) {
                return true;
            }
        }

        return false;
    }

    private function format(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
    }

    private function store(PipelineContext $context, array $claim, ?string $reason, int $attempt): void
    {
        if ($claim['product_id'] <= 0 || ! in_array($claim['product_id'], array_map(fn ($p) => $p->product->id, $context->picks), true)) {
            return;   // can't store a claim about a product that isn't a pick (foreign key); it's counted as rejected
        }
        Claim::create([
            'search_session_id' => $context->session->id,
            'product_id' => $claim['product_id'],
            'text' => mb_substr($claim['text'], 0, 1000),
            'cites' => $claim['cites'] ? mb_substr($claim['cites'], 0, 10) : null,
            'facts' => $claim['facts'],
            'status' => $reason === null ? 'verified' : 'rejected',
            'attempt' => $attempt,
            'reason' => $reason,
        ]);
    }
}
