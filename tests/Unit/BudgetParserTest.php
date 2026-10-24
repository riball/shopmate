<?php

namespace Tests\Unit;

use App\Services\Pipeline\BudgetParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BudgetParserTest extends TestCase
{
    public static function budgets(): array
    {
        return [
            'rupee sign with commas' => ['earbuds under ₹2,000 with a mic', 2000.0],
            'k suffix' => ['5G phone under 20k', 20000.0],
            'lakh' => ['laptop within 1.5 lakh', 150000.0],
            'indian grouping' => ['budget ₹1,50,000 for a laptop', 150000.0],
            'rs prefix' => ['watch below rs 4500', 4500.0],
            'thousand word' => ['shoes around 3 thousand', 3000.0],
            'suffix only' => ['a 15k phone with a good camera', 15000.0],
        ];
    }

    #[DataProvider('budgets')]
    public function test_reads_budgets(string $text, float $expected): void
    {
        $this->assertSame($expected, (new BudgetParser)->parse($text));
    }

    public static function notBudgets(): array
    {
        return [
            'ram is not money' => ['phone with 8 GB RAM'],
            'battery is not money' => ['phone with 5000 mAh battery'],
            'under + unit' => ['laptop under 2 kg'],
            'no number' => ['something for my dad'],
        ];
    }

    #[DataProvider('notBudgets')]
    public function test_ignores_product_numbers(string $text): void
    {
        $this->assertNull((new BudgetParser)->parse($text));
    }
}
