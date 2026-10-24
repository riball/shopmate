<?php

namespace App\Services\Catalog\Sources;

use RuntimeException;

/**
 * Fictional catalog (made-up brands, stores and prices) that is safe to
 * publish on the live demo. Lives in database/data/demo_catalog.json.
 */
class DemoSource implements CatalogSource
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= database_path('data/demo_catalog.json');
    }

    public function name(): string
    {
        return 'demo';
    }

    public function listings(): iterable
    {
        if (! is_file($this->path)) {
            throw new RuntimeException("Demo catalog not found at {$this->path}");
        }

        $data = json_decode(file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);

        foreach ($data['products'] as $listing) {
            foreach ($listing['offers'] as &$offer) {
                $store = $data['stores'][$offer['store']];
                $offer += [
                    'store_name' => $store['name'],
                    'store_url' => $store['website'] ?? null,
                    'is_demo' => true,
                    'in_stock' => true,
                    'original_currency' => 'INR',
                    'original_price' => null,
                    'fx_rate' => null,
                ];
            }
            unset($offer);

            yield $listing + ['captured_on' => $data['captured_on'] ?? null];
        }
    }
}
