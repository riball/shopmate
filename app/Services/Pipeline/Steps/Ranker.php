<?php

namespace App\Services\Pipeline\Steps;

use App\Services\Catalog\ReviewWeightedRating;
use App\Services\Pipeline\Candidate;
use App\Services\Pipeline\PipelineContext;

/**
 * Rank (code only):
 *   score = 0.7 × (review-weighted rating / 5) + 0.3 × (share of must-haves confirmed)
 * with the lower price winning ties. Colour/storage variants of the same model
 * are collapsed so the top 3 are three different products.
 */
class Ranker implements PipelineStep
{
    public function __construct(private ReviewWeightedRating $weighted) {}

    public function handle(PipelineContext $context): StepOutcome
    {
        $picks = $this->rank($context->candidates);
        $context->picks = $picks;

        $context->rankStepId = $context->trace->tool('rank',
            'Order the matches fairly: trust ratings with many reviews more, prefer confirmed must-haves, and break ties on price.',
            'rank(candidates='.count($context->candidates).')',
            sprintf('Collapsed colour/storage variants and kept the top %d of %d. Order: %s.',
                count($picks), count($context->candidates),
                implode(', ', array_map(fn (Candidate $c) => "#{$c->rank} {$c->product->name}", $picks))),
            array_map(fn (Candidate $c) => $c->product->id, $picks),
            ['scores' => array_map(fn (Candidate $c) => [
                'product_id' => $c->product->id,
                'rank' => $c->rank,
                'score' => round($c->score, 4),
                'weighted_rating' => $c->product->weighted_rating,
                'confirmed' => $c->confirmedCount(),
            ], $picks)],
        );

        return StepOutcome::Continue;
    }

    /**
     * @param  Candidate[]  $candidates
     * @return Candidate[]
     */
    public function rank(array $candidates, ?int $limit = null): array
    {
        $limit ??= (int) config('shopmate.pipeline.results', 3);
        $weights = config('shopmate.pipeline.score_weights');

        $ratings = array_filter(array_map(fn (Candidate $c) => $c->product->rating, $candidates));
        $mean = $ratings ? array_sum($ratings) / count($ratings) : 4.0;

        foreach ($candidates as $candidate) {
            $weighted = $candidate->product->weighted_rating
                ?? $this->weighted->calculate($candidate->product->rating, $candidate->product->review_count, $mean);
            $candidate->score = $weights['rating'] * ($weighted / 5) + $weights['requirements'] * $candidate->confirmedRatio();
        }

        usort($candidates, fn (Candidate $a, Candidate $b) => [$b->score, $a->price()] <=> [$a->score, $b->price()]);

        // Keep only the best-scoring variant of each model.
        $seen = [];
        $picks = [];
        foreach ($candidates as $candidate) {
            $group = $candidate->product->model_group;
            if (isset($seen[$group])) {
                continue;
            }
            $seen[$group] = true;
            $candidate->rank = count($picks) + 1;
            $picks[] = $candidate;
            if (count($picks) === $limit) {
                break;
            }
        }

        return $picks;
    }
}
