<?php

namespace App\Services\Pipeline;

use App\Models\Category;
use App\Models\SearchSession;

/**
 * Everything the pipeline steps share for one search. Each step reads what
 * earlier steps produced and adds its own results.
 */
class PipelineContext
{
    public ?Category $category = null;

    public ?float $originalBudget = null;   // what the shopper said; verification always uses this

    public ?float $effectiveBudget = null;  // may be raised by a replan

    /** @var Requirement[] */
    public array $requirements = [];

    /** @var string[] requirement keys given up by replans */
    public array $dropped = [];

    /** @var array<int, array> */
    public array $replans = [];

    public ?string $clarifyQuestion = null;

    /** @var Candidate[] */
    public array $candidates = [];

    /** @var Candidate[] the final top picks */
    public array $picks = [];

    public ?string $searchStepId = null;

    public ?string $rankStepId = null;

    /** @var array<int, array> claims drafted by the AI, waiting for verification */
    public array $drafts = [];

    /** @var array<int, array> verified claims */
    public array $claims = [];

    public function __construct(
        public readonly SearchSession $session,
        public readonly Trace $trace,
    ) {}

    /** Requirements still in force (not dropped by a replan). */
    public function activeRequirements(): array
    {
        return array_values(array_filter($this->requirements, fn (Requirement $r) => ! in_array($r->key, $this->dropped, true)));
    }

    /** The "work order" shown at the top of the results. */
    public function workOrder(): array
    {
        return [
            'category' => $this->category?->slug,
            'category_name' => $this->category?->name,
            'budget' => $this->originalBudget,
            'effective_budget' => $this->effectiveBudget,
            'must_haves' => array_map(fn (Requirement $r) => [
                'key' => $r->key,
                'label' => $r->label,
                'supported' => $r->isSupported(),
                'dropped' => in_array($r->key, $this->dropped, true),
            ], $this->requirements),
        ];
    }
}
