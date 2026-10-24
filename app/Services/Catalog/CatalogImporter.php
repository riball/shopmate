<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Models\SpecFact;
use App\Models\Store;
use App\Services\Catalog\Sources\CatalogSource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Loads listings from any CatalogSource into MySQL:
 * products, store offers, typed spec facts and price history.
 * Running it again updates existing rows instead of duplicating them.
 */
class CatalogImporter
{
    /** @var array<string, Category> */
    private array $categories = [];

    /** @var array<string, Store> */
    private array $stores = [];

    public function __construct(
        private SpecNormalizer $normalizer,
        private ModelGrouper $grouper,
        private ReviewWeightedRating $weighted,
    ) {}

    /** @return array{created: int, updated: int, offers: int, facts: int, conflicts: int, skipped: int} */
    public function import(CatalogSource $source, ?callable $progress = null): array
    {
        $report = ['created' => 0, 'updated' => 0, 'offers' => 0, 'facts' => 0, 'conflicts' => 0, 'skipped' => 0];
        $touchedCategories = [];

        foreach ($source->listings() as $listing) {
            if (! config("shopmate_specs.{$listing['category']}") || empty($listing['offers'])) {
                $report['skipped']++;

                continue;
            }

            DB::transaction(function () use ($source, $listing, &$report, &$touchedCategories) {
                $category = $this->category($listing['category']);
                $touchedCategories[$category->id] = $category;

                $product = Product::firstOrNew(['source' => $source->name(), 'external_id' => $listing['external_id']]);
                $report[$product->exists ? 'updated' : 'created']++;

                $product->fill([
                    'category_id' => $category->id,
                    'name' => Str::limit($listing['name'], 250, ''),
                    'brand' => $listing['brand'] ?? null,
                    'model_group' => $this->grouper->group($listing['name']),
                    'image_url' => $listing['image_url'] ?? null,
                    'rating' => $listing['rating'] ?? null,
                    'review_count' => (int) ($listing['review_count'] ?? 0),
                    'captured_on' => $listing['captured_on'] ?? null,
                ]);
                $product->slug ??= $this->uniqueSlug($listing['name'], $source->name());
                $product->save();

                $facts = $this->normalizer->normalize($category->slug, $listing['specs'] ?? []);
                $this->replaceFacts($product, $facts);
                $report['facts'] += count($facts);
                $report['conflicts'] += count(array_filter($facts, fn ($f) => $f['conflict']));

                foreach ($listing['offers'] as $offerData) {
                    $this->upsertOffer($product, $offerData);
                    $report['offers']++;
                }

                $product->refreshBestOffer();
            });

            if ($progress) {
                $progress();
            }
        }

        foreach ($touchedCategories as $category) {
            $this->recalculateWeightedRatings($category);
            Cache::forget("facets:{$category->id}");
        }

        return $report;
    }

    /** Update one store's price, writing history only when the price actually changes. */
    public function recordPrice(ProductOffer $offer, float $price, ?bool $inStock = null): bool
    {
        $changed = ! $offer->exists || abs($offer->price - $price) >= 0.01;

        $offer->price = $price;
        if ($inStock !== null) {
            $offer->in_stock = $inStock;
        }
        $offer->last_checked_at = now();
        $offer->save();

        if ($changed) {
            PriceHistory::create(['product_offer_id' => $offer->id, 'price' => $price, 'recorded_at' => now()]);
        }

        return $changed;
    }

    public function recalculateWeightedRatings(Category $category): void
    {
        $mean = (float) (Product::where('category_id', $category->id)->whereNotNull('rating')->avg('rating') ?? 4.0);

        Product::where('category_id', $category->id)
            ->select(['id', 'rating', 'review_count'])
            ->chunkById(200, function ($products) use ($mean) {
                foreach ($products as $product) {
                    Product::whereKey($product->id)->update([
                        'weighted_rating' => $this->weighted->calculate($product->rating, $product->review_count, $mean),
                    ]);
                }
            });
    }

    private function upsertOffer(Product $product, array $data): void
    {
        $store = $this->store($data);
        $offer = ProductOffer::firstOrNew(['product_id' => $product->id, 'store_id' => $store->id]);
        $offer->fill([
            'mrp' => $data['mrp'] ?? null,
            'url' => $data['url'] ?? null,
            'original_currency' => $data['original_currency'] ?? 'INR',
            'original_price' => $data['original_price'] ?? null,
            'fx_rate' => $data['fx_rate'] ?? null,
        ]);

        $this->recordPrice($offer, (float) $data['price'], (bool) ($data['in_stock'] ?? true));
    }

    private function replaceFacts(Product $product, array $facts): void
    {
        SpecFact::where('product_id', $product->id)->delete();
        $now = now();
        $rows = [];
        foreach ($facts as $key => $fact) {
            $rows[] = [
                'product_id' => $product->id,
                'key' => $key,
                'num_value' => $fact['num'],
                'text_value' => $fact['text'],
                'bool_value' => $fact['bool'],
                'raw_label' => Str::limit($fact['raw_label'], 250, ''),
                'raw_value' => $fact['raw_value'],
                'is_conflict' => $fact['conflict'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($rows) {
            SpecFact::insert($rows);   // one query instead of one per fact
        }
    }

    private function category(string $slug): Category
    {
        return $this->categories[$slug] ??= Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => config("shopmate_specs.{$slug}.name", Str::headline($slug))],
        );
    }

    private function store(array $offer): Store
    {
        return $this->stores[$offer['store']] ??= Store::firstOrCreate(
            ['slug' => $offer['store']],
            ['name' => $offer['store_name'] ?? Str::headline($offer['store']), 'website' => $offer['store_url'] ?? null, 'is_demo' => (bool) ($offer['is_demo'] ?? false)],
        );
    }

    private function uniqueSlug(string $name, string $source): string
    {
        $base = Str::slug(Str::limit($name, 80, ''));
        $slug = $base;
        $i = 2;
        while (Product::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$source}".($i > 2 ? "-{$i}" : '');
            $i++;
        }

        return $slug;
    }
}
