<?php

namespace App\Services\Catalog\Sources;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Your private Flipkart snapshot (Apify export), one JSON file per category:
 *   storage/app/private/flipkart/smartphones.json
 *   storage/app/private/flipkart/running-shoes.json   ...
 *
 * That folder is git-ignored, so the data never reaches GitHub or the public demo.
 * Field names differ between scrapers, so each field is read from a list of
 * likely names. Add yours to the lists below if your export uses others.
 */
class LocalJsonSource implements CatalogSource
{
    private const FIELDS = [
        'id' => ['product_id', 'productId', 'id', 'pid', 'itemId', 'sku'],
        'name' => ['title', 'name', 'product_name', 'productName'],
        'brand' => ['brand', 'brandName'],
        'price' => ['price', 'selling_price', 'sellingPrice', 'final_price', 'finalPrice', 'currentPrice'],
        'mrp' => ['mrp', 'original_price', 'originalPrice', 'maximumRetailPrice'],
        'rating' => ['rating', 'average_rating', 'averageRating', 'stars'],
        'reviews' => ['ratings_count', 'ratingsCount', 'rating_count', 'review_count', 'reviewsCount', 'reviews_count', 'numberOfRatings'],
        'url' => ['url', 'product_url', 'productUrl', 'link'],
        'image' => ['image', 'image_url', 'imageUrl', 'thumbnail', 'images.0'],
        'specs' => ['specifications', 'specs', 'attributes', 'details'],
        'in_stock' => ['in_stock', 'inStock', 'available', 'availability'],
    ];

    public function __construct(private ?string $directory = null, private ?string $capturedOn = null)
    {
        $this->directory ??= config('shopmate.sources.local_path');
    }

    public function name(): string
    {
        return 'local';
    }

    public function listings(): iterable
    {
        $files = glob(rtrim($this->directory, '/\\').DIRECTORY_SEPARATOR.'*.json') ?: [];
        if ($files === []) {
            throw new RuntimeException("No JSON files found in {$this->directory}. Put your private Flipkart export there (one file per category).");
        }

        foreach ($files as $file) {
            $category = Str::slug(str_replace('_', '-', pathinfo($file, PATHINFO_FILENAME)));
            if (! config("shopmate_specs.{$category}")) {
                continue;   // file name doesn't match a known category
            }
            $rows = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $capturedOn = $this->capturedOn ?? date('Y-m-d', filemtime($file));

            foreach (Arr::isList($rows) ? $rows : ($rows['items'] ?? $rows['data'] ?? []) as $row) {
                $listing = $this->toListing($row, $category, $capturedOn);
                if ($listing !== null) {
                    yield $listing;
                }
            }
        }
    }

    private function toListing(array $row, string $category, string $capturedOn): ?array
    {
        $name = $this->pick($row, 'name');
        $price = $this->money($this->pick($row, 'price'));
        if (! $name || ! $price) {
            return null;   // can't compare a product without a name and a price
        }
        $url = $this->pick($row, 'url');
        $id = $this->pick($row, 'id') ?? md5($url ?: $name);
        $inStock = $this->pick($row, 'in_stock');

        return [
            'external_id' => (string) $id,
            'name' => trim($name),
            'brand' => $this->pick($row, 'brand') ?: Str::before(trim($name), ' '),
            'category' => $category,
            'image_url' => $this->pick($row, 'image'),
            'rating' => is_numeric($r = $this->pick($row, 'rating')) ? (float) $r : null,
            'review_count' => (int) preg_replace('/\D/', '', (string) ($this->pick($row, 'reviews') ?? 0)),
            'captured_on' => $capturedOn,
            'specs' => $this->flattenSpecs($this->pick($row, 'specs') ?? []),
            'offers' => [[
                'store' => 'flipkart',
                'store_name' => 'Flipkart',
                'store_url' => 'https://www.flipkart.com',
                'is_demo' => false,
                'price' => $price,
                'mrp' => $this->money($this->pick($row, 'mrp')),
                'url' => $url,
                'in_stock' => $inStock === null ? true : ! in_array(strtolower((string) $inStock), ['0', 'false', 'no', 'out of stock'], true),
                'original_currency' => 'INR',
                'original_price' => null,
                'fx_rate' => null,
            ]],
        ];
    }

    /**
     * Specs arrive as {"RAM": "8 GB"}, as [{"key": "RAM", "value": "8 GB"}],
     * or grouped: [{"title": "Memory", "specs": [{"name": "RAM", "value": "8 GB"}]}].
     */
    private function flattenSpecs(mixed $specs): array
    {
        if (! is_array($specs)) {
            return [];
        }
        if (! Arr::isList($specs)) {
            return array_filter($specs, fn ($v) => is_scalar($v) || is_array($v));
        }

        $flat = [];
        foreach ($specs as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = $item['key'] ?? $item['name'] ?? $item['label'] ?? null;
            if ($label !== null && array_key_exists('value', $item)) {
                $flat[$label] = is_array($item['value']) ? implode(', ', $item['value']) : $item['value'];

                continue;
            }
            foreach (['specs', 'details', 'attributes', 'values'] as $nested) {
                if (isset($item[$nested]) && is_array($item[$nested])) {
                    $flat += $this->flattenSpecs($item[$nested]);
                }
            }
        }

        return $flat;
    }

    private function pick(array $row, string $field): mixed
    {
        foreach (self::FIELDS[$field] as $candidate) {
            $value = Arr::get($row, $candidate);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function money(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $number = (float) preg_replace('/[^\d.]/', '', (string) $value);

        return $number > 0 ? $number : null;
    }
}
