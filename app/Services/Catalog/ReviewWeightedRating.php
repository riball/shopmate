<?php

namespace App\Services\Catalog;

/**
 * Review-weighted rating (a Bayesian average): pulls ratings with few reviews
 * towards a starting value, so 5.0 stars from 3 reviews doesn't beat
 * 4.1 stars from 18,000.
 *
 *   weighted = (reviews * rating + m * prior) / (reviews + m)
 *   prior    = category average - 0.5   (an unproven product starts slightly below average)
 *
 * m is how many reviews a product needs before its own rating "counts" as much
 * as the prior (config shopmate.pipeline.rating_prior_reviews).
 */
class ReviewWeightedRating
{
    public function __construct(private ?int $priorReviews = null, private ?float $priorPenalty = null)
    {
        $this->priorReviews ??= (int) config('shopmate.pipeline.rating_prior_reviews', 50);
        $this->priorPenalty ??= (float) config('shopmate.pipeline.rating_prior_penalty', 0.5);
    }

    public function calculate(?float $rating, int $reviews, float $categoryMean): float
    {
        $prior = max(0.0, $categoryMean - $this->priorPenalty);
        if ($rating === null || $reviews <= 0) {
            return round($prior, 3);
        }
        $m = $this->priorReviews;

        return round(($reviews * $rating + $m * $prior) / ($reviews + $m), 3);
    }
}
