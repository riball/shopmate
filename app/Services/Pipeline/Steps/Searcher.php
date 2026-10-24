<?php

namespace App\Services\Pipeline\Steps;

use App\Models\Category;
use App\Models\Product;
use App\Services\Pipeline\Candidate;
use App\Services\Pipeline\PipelineContext;
use App\Services\Pipeline\Requirement;
use Illuminate\Support\Collection;

/**
 * Search (code only): looks up the category, removes anything over budget and
 * checks every must-have against each product's typed spec facts.
 *   met         -> kept, shown as ✓
 *   not met     -> removed
 *   unknown / conflict / unsupported -> kept, shown as "couldn't confirm"
 */
class Searcher implements PipelineStep
{
    /** @var array<int, Collection<int, Product>> category id => products, loaded once per search */
    private array $pools = [];

    public function handle(PipelineContext $context): StepOutcome
    {
        $result = $this->find($context->category, $context->effectiveBudget, $context->activeRequirements());
        $context->candidates = $result['candidates'];

        $budgetText = $context->effectiveBudget ? '₹'.number_format($context->effectiveBudget) : 'any price';
        $context->searchStepId = $context->trace->tool('search',
            'Find real listings that fit the budget and check each must-have against the listing specs.',
            sprintf('search_catalog(category="%s", max_price=%s, must_haves=%d)',
                $context->category->slug, $context->effectiveBudget ?? 'none', count($context->activeRequirements())),
            sprintf('%d listings in %s, %d within %s, %d left after removing products that clearly fail a must-have.',
                $result['in_category'], $context->category->name, $result['in_budget'], $budgetText, count($result['candidates'])),
            array_map(fn (Candidate $c) => $c->product->id, $result['candidates']),
            ['removed_by_requirement' => $result['removed_by_requirement']],
        );

        return StepOutcome::Continue;
    }

    /**
     * Pure search, also used by the Replanner to count what each change would unlock.
     *
     * @param  Requirement[]  $requirements
     * @return array{in_category: int, in_budget: int, candidates: Candidate[], removed_by_requirement: array<string, int>}
     */
    public function find(Category $category, ?float $budget, array $requirements): array
    {
        $pool = $this->pool($category);
        $inBudget = $budget === null ? $pool : $pool->filter(fn (Product $p) => $p->best_price <= $budget);

        $candidates = [];
        $removed = [];
        foreach ($inBudget as $product) {
            $facts = $product->factMap();
            $checklist = [];
            $failed = null;
            foreach ($requirements as $requirement) {
                $status = $requirement->evaluate($requirement->spec ? ($facts[$requirement->spec] ?? null) : null);
                if ($status === Requirement::NOT_MET) {
                    $failed = $requirement->key;
                    break;
                }
                $checklist[$requirement->key] = ['label' => $requirement->label, 'status' => $status];
            }

            if ($failed !== null) {
                $removed[$failed] = ($removed[$failed] ?? 0) + 1;

                continue;
            }
            $candidates[] = new Candidate($product, $product->bestOffer, $checklist);
        }

        return [
            'in_category' => $pool->count(),
            'in_budget' => $inBudget->count(),
            'candidates' => $candidates,
            'removed_by_requirement' => $removed,
        ];
    }

    /** Cheapest price among products that pass these requirements, ignoring budget. */
    public function cheapestPassing(Category $category, array $requirements): ?float
    {
        $prices = array_map(fn (Candidate $c) => $c->price(), $this->find($category, null, $requirements)['candidates']);

        return $prices ? min($prices) : null;
    }

    private function pool(Category $category): Collection
    {
        // Eager loading: specs and best offer come in 3 queries total, not 1 + 2 per product (the "N+1 problem").
        return $this->pools[$category->id] ??= Product::query()
            ->with(['specFacts', 'bestOffer.store'])
            ->where('category_id', $category->id)
            ->whereNotNull('best_offer_id')
            ->get();
    }
}
