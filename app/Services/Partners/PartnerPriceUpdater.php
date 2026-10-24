<?php

namespace App\Services\Partners;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Catalog\CatalogImporter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Applies a partner's price update. A partner can only change offers that
 * belong to its own store: asking for another store's product is skipped,
 * never applied (object-level authorization).
 */
final class PartnerPriceUpdater
{
    public function __construct(private CatalogImporter $importer) {}

    /**
     * @param  array<int, array{product: string, price: float|int|string, in_stock?: bool}>  $items
     * @return array{results: array<int, array<string, mixed>>, updated: int, unchanged: int, skipped: int}
     */
    public function apply(Partner $partner, array $items): array
    {
        return DB::transaction(function () use ($partner, $items) {
            $slugs = array_values(array_unique(array_column($items, 'product')));

            // Lock this store's matching offers so two updates at once cannot interleave.
            $offers = ProductOffer::query()
                ->where('store_id', $partner->store_id)
                ->whereHas('product', fn ($q) => $q->whereIn('slug', $slugs))
                ->with('product:id,slug')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (ProductOffer $offer) => $offer->product->slug);

            $results = [];
            $touched = [];
            $counts = ['updated' => 0, 'unchanged' => 0, 'skipped' => 0];

            foreach ($items as $item) {
                $offer = $offers->get($item['product']);
                if ($offer === null) {
                    $results[] = ['product' => $item['product'], 'status' => 'skipped', 'reason' => 'not_sold_by_your_store'];
                    $counts['skipped']++;

                    continue;
                }

                $old = $offer->price;
                $changed = $this->importer->recordPrice($offer, (float) $item['price'], isset($item['in_stock']) ? (bool) $item['in_stock'] : null);
                $touched[$offer->product_id] = true;

                $status = $changed ? 'updated' : 'unchanged';
                $counts[$status]++;
                $results[] = ['product' => $item['product'], 'status' => $status, 'old_price' => $old, 'new_price' => $offer->price, 'in_stock' => $offer->in_stock];
            }

            Product::whereIn('id', array_keys($touched))->get()->each->refreshBestOffer();

            if ($touched !== []) {
                $this->forgetPriceCaches();
            }

            return ['results' => $results] + $counts;
        });
    }

    private function forgetPriceCaches(): void
    {
        Cache::forget('home:categories');
        Cache::forget('nav:categories');
        Cache::forget('sitemap.xml');
        Category::pluck('id')->each(fn ($id) => Cache::forget("facets:{$id}"));
    }
}
