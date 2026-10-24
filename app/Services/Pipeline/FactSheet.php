<?php

namespace App\Services\Pipeline;

use App\Models\SpecFact;

/**
 * The ground truth about each pick, taken from the database (never from the AI).
 * The Explainer hands this to the AI to write from, and the Verifier checks
 * every claim against the very same data.
 */
class FactSheet
{
    /** @var array<int, array> product id => facts */
    private array $sheets = [];

    public function __construct(private PipelineContext $context)
    {
        foreach ($context->picks as $pick) {
            $this->sheets[$pick->product->id] = $this->build($pick);
        }
    }

    public function all(): array
    {
        return $this->sheets;
    }

    public function for(int $productId): ?array
    {
        return $this->sheets[$productId] ?? null;
    }

    private function build(Candidate $pick): array
    {
        $product = $pick->product;
        $budget = $this->context->originalBudget;

        $specs = [];
        $specValues = [];
        /** @var SpecFact $fact */
        foreach ($product->factMap() as $key => $fact) {
            if ($fact->is_conflict || $fact->value() === null) {
                continue;
            }
            $specs[$key] = $fact->value();
            $specValues[$key] = $fact->display();
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'store' => $pick->offer->store->name,
            'rank' => $pick->rank,
            'price' => $pick->price(),
            'rating' => $product->rating,
            'review_count' => $product->review_count,
            'budget' => $budget,
            'within_budget' => $budget === null ? null : $pick->price() <= $budget,
            'specs' => $specs,
            'spec_display' => $specValues,
            'requirements' => array_map(fn ($r) => $r['status'], $pick->checklist),
            'requirement_labels' => array_map(fn ($r) => $r['label'], $pick->checklist),
            'steps' => ['search' => $this->context->searchStepId, 'rank' => $this->context->rankStepId],
        ];
    }

    /** The data the AI sees: the same facts, without internal-only fields. */
    public function forPrompt(): array
    {
        return array_values(array_map(function (array $s) {
            unset($s['spec_display']);

            return $s;
        }, $this->sheets));
    }
}
