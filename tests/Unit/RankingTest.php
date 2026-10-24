<?php

namespace Tests\Unit;

use App\Models\SpecFact;
use App\Services\Catalog\ReviewWeightedRating;
use App\Services\Pipeline\Requirement;
use PHPUnit\Framework\TestCase;

class RankingTest extends TestCase
{
    public function test_few_reviews_do_not_beat_many_reviews(): void
    {
        $weighted = new ReviewWeightedRating(priorReviews: 50, priorPenalty: 0.5);
        $mean = 4.1;

        $threeFiveStars = $weighted->calculate(5.0, 3, $mean);
        $thousandsOfFourPointFive = $weighted->calculate(4.5, 4000, $mean);

        $this->assertGreaterThan($threeFiveStars, $thousandsOfFourPointFive);
    }

    public function test_requirement_statuses(): void
    {
        $ram = new Requirement('min_ram_gb', 'At least 8 GB RAM', 'ram_gb', '>=', 8);

        $this->assertSame(Requirement::MET, $ram->evaluate(new SpecFact(['num_value' => 12])));
        $this->assertSame(Requirement::NOT_MET, $ram->evaluate(new SpecFact(['num_value' => 4])));
        $this->assertSame(Requirement::UNKNOWN, $ram->evaluate(null));
        $this->assertSame(Requirement::CONFLICT, $ram->evaluate(new SpecFact(['is_conflict' => true])));
        $this->assertSame(Requirement::UNSUPPORTED, Requirement::unsupported('great bass')->evaluate(null));
    }

    public function test_women_does_not_count_as_men(): void
    {
        $forMen = new Requirement('for_men', 'Made for men', 'gender', 'one_of', ['men', 'unisex']);

        $this->assertSame(Requirement::NOT_MET, $forMen->evaluate(new SpecFact(['text_value' => 'women'])));
        $this->assertSame(Requirement::MET, $forMen->evaluate(new SpecFact(['text_value' => 'unisex'])));
    }
}
