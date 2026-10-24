<?php

namespace App\Services\Catalog\Sources;

/**
 * A place product listings come from. Every source returns listings in the
 * same shape, so the importer doesn't care where the data came from:
 *
 * [
 *   'external_id' => string, 'name' => string, 'brand' => ?string,
 *   'category' => 'earphones' | 'smartphones' | 'laptops' | 'smartwatches' | 'running-shoes',
 *   'image_url' => ?string, 'rating' => ?float, 'review_count' => int, 'captured_on' => ?string (Y-m-d),
 *   'specs' => [label => value, ...],
 *   'offers' => [[
 *       'store' => slug, 'store_name' => string, 'store_url' => ?string, 'is_demo' => bool,
 *       'price' => float (rupees), 'mrp' => ?float, 'url' => ?string, 'in_stock' => bool,
 *       'original_currency' => 'INR', 'original_price' => ?float, 'fx_rate' => ?float,
 *   ], ...],
 * ]
 */
interface CatalogSource
{
    /** Short name stored on products ("demo", "local", "dummyjson"). */
    public function name(): string;

    /** @return iterable<array> */
    public function listings(): iterable;
}
