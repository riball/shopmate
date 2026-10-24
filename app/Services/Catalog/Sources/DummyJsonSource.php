<?php

namespace App\Services\Catalog\Sources;

use App\Services\Currency\FrankfurterClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Public demo API (dummyjson.com). Prices are in US dollars, so each one is
 * converted to rupees with Frankfurter and labelled "approx." on the site.
 * Its products have few specs, so most must-haves show as "couldn't confirm",
 * which is exactly how the pipeline should behave with thin data.
 */
class DummyJsonSource implements CatalogSource
{
    /** our category => [dummyjson category, optional title filter] */
    private const MAP = [
        'smartphones' => [['smartphones', null]],
        'laptops' => [['laptops', null]],
        'smartwatches' => [['mens-watches', '/smart|watch/i'], ['womens-watches', '/smart|watch/i']],
        'earphones' => [['mobile-accessories', '/airpods|earphone|buds|headphone|earbud/i']],
        'running-shoes' => [['mens-shoes', '/sneaker|shoe|running|sports/i'], ['womens-shoes', '/sneaker|shoe|running|sports/i']],
    ];

    public function __construct(private FrankfurterClient $fx) {}

    public function name(): string
    {
        return 'dummyjson';
    }

    public function listings(): iterable
    {
        $rate = $this->fx->rate('USD', 'INR');
        $base = rtrim(config('shopmate.sources.dummyjson_url'), '/');

        foreach (self::MAP as $category => $sources) {
            foreach ($sources as [$remoteCategory, $titleFilter]) {
                $response = Http::timeout(20)->retry(2, 500, throw: false)
                    ->get("{$base}/products/category/{$remoteCategory}", ['limit' => 100]);
                if (! $response->successful()) {
                    throw new RuntimeException("DummyJSON returned HTTP {$response->status()} for {$remoteCategory}");
                }

                foreach ($response->json('products', []) as $item) {
                    if ($titleFilter && ! preg_match($titleFilter, $item['title'] ?? '')) {
                        continue;
                    }
                    yield $this->toListing($item, $category, $rate, $base);
                }
            }
        }
    }

    private function toListing(array $item, string $category, float $rate, string $base): array
    {
        $usd = (float) $item['price'];
        $discount = (float) ($item['discountPercentage'] ?? 0);
        $sellingUsd = round($usd * (1 - $discount / 100), 2);

        return [
            'external_id' => (string) $item['id'],
            'name' => $item['title'],
            'brand' => $item['brand'] ?? null,
            'category' => $category,
            'image_url' => $item['thumbnail'] ?? null,
            'rating' => isset($item['rating']) ? (float) $item['rating'] : null,
            'review_count' => count($item['reviews'] ?? []),
            'captured_on' => now()->toDateString(),
            'specs' => array_filter([
                'Weight' => isset($item['weight']) ? $item['weight'].' g' : null,
            ]),
            'offers' => [[
                'store' => 'dummyjson',
                'store_name' => 'DummyJSON (demo API)',
                'store_url' => 'https://dummyjson.com',
                'is_demo' => true,
                'price' => round($sellingUsd * $rate),
                'mrp' => round($usd * $rate),
                'url' => "{$base}/products/{$item['id']}",
                'in_stock' => ($item['stock'] ?? 1) > 0,
                'original_currency' => 'USD',
                'original_price' => $sellingUsd,
                'fx_rate' => $rate,
            ]],
        ];
    }
}
