<?php

namespace App\Services\Pipeline;

use App\Models\Product;
use App\Models\ProductOffer;

/** A product that survived the search, with its requirement checklist and score. */
final class Candidate
{
    public float $score = 0.0;

    public int $rank = 0;

    /**
     * @param  array<string, array{label: string, status: string}>  $checklist
     */
    public function __construct(
        public readonly Product $product,
        public readonly ProductOffer $offer,
        public readonly array $checklist,
    ) {}

    public function price(): float
    {
        return (float) $this->offer->price;
    }

    public function confirmedCount(): int
    {
        return count(array_filter($this->checklist, fn ($r) => $r['status'] === Requirement::MET));
    }

    /** Share of the shopper's must-haves the listing clearly confirms (1.0 when none were asked). */
    public function confirmedRatio(): float
    {
        return $this->checklist === [] ? 1.0 : $this->confirmedCount() / count($this->checklist);
    }
}
