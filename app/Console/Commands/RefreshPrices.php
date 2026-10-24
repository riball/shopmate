<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\Sources\DummyJsonSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Scheduled every 6 hours. Re-fetches prices from sources that can be
 * re-fetched (DummyJSON) and records history only when a price changes.
 *
 * The fictional demo catalog has no live source, so with --simulate-demo it
 * moves demo prices slightly to keep the price-history charts realistic.
 * Real data is never simulated.
 */
class RefreshPrices extends Command
{
    protected $signature = 'prices:refresh {--simulate-demo : Nudge fictional demo prices so the demo shows price history}';

    protected $description = 'Refresh store prices and record price history';

    public function handle(CatalogImporter $importer): int
    {
        $changed = 0;

        if (Product::where('source', 'dummyjson')->exists()) {
            try {
                $report = $importer->import(app(DummyJsonSource::class));
                $this->info("DummyJSON: {$report['updated']} products refreshed.");
            } catch (Throwable $e) {
                $this->warn('DummyJSON refresh failed: '.$e->getMessage());
            }
        }

        if ($this->option('simulate-demo')) {
            ProductOffer::whereHas('product', fn ($q) => $q->where('source', 'demo'))
                ->chunkById(200, function ($offers) use ($importer, &$changed) {
                    foreach ($offers as $offer) {
                        // Most prices stay put; a few move by up to ±4%, rounded to end in 9.
                        $move = random_int(1, 10) <= 2 ? random_int(-4, 4) / 100 : 0;
                        $price = $move === 0 ? $offer->price : max(99, round($offer->price * (1 + $move) / 10) * 10 - 1);
                        if ($importer->recordPrice($offer, (float) $price)) {
                            $changed++;
                        }
                    }
                });

            Product::where('source', 'demo')->chunkById(200, fn ($products) => $products->each->refreshBestOffer());
            $this->info("Demo catalog: {$changed} simulated price changes.");
        }

        // Cached data that depends on prices.
        Cache::forget('home:categories');
        Cache::forget('nav:categories');
        Cache::forget('sitemap.xml');
        Category::pluck('id')->each(fn ($id) => Cache::forget("facets:{$id}"));

        return self::SUCCESS;
    }
}
