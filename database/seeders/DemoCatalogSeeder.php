<?php

namespace Database\Seeders;

use App\Models\PriceHistory;
use App\Models\ProductOffer;
use App\Services\Catalog\CatalogImporter;
use App\Services\Catalog\Sources\DemoSource;
use Illuminate\Database\Seeder;

/**
 * Imports the fictional demo catalog and gives each demo offer 60 days of
 * made-up price history so the charts have something to show.
 * Only demo (fictional) data is ever given simulated history.
 */
class DemoCatalogSeeder extends Seeder
{
    public function run(CatalogImporter $importer): void
    {
        $report = $importer->import(new DemoSource);
        $this->command?->info("Demo catalog: {$report['created']} created, {$report['updated']} updated, {$report['offers']} offers, {$report['conflicts']} contradiction(s).");

        mt_srand(7);
        $offers = ProductOffer::whereHas('product', fn ($q) => $q->where('source', 'demo'))->get();

        foreach ($offers as $offer) {
            PriceHistory::where('product_offer_id', $offer->id)->delete();

            $rows = [];
            $price = $offer->price * (1 + mt_rand(4, 12) / 100);   // start a bit higher than today
            for ($daysAgo = 60; $daysAgo >= 1; $daysAgo -= mt_rand(4, 9)) {
                $rows[] = ['product_offer_id' => $offer->id, 'price' => round($price / 10) * 10 - 1, 'recorded_at' => now()->subDays($daysAgo)];
                $price *= 1 + mt_rand(-5, 2) / 100;                  // drifts down over time, with a few bumps
            }
            $rows[] = ['product_offer_id' => $offer->id, 'price' => $offer->price, 'recorded_at' => now()];
            PriceHistory::insert($rows);
        }
    }
}
