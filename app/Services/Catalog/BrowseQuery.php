<?php

namespace App\Services\Catalog;

use App\Models\Category;
use App\Models\Product;
use App\Services\Pipeline\RequirementCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds the SQL for the browse page's filters and sorting.
 * Must-have filters become EXISTS sub-queries on spec_facts, which use the
 * (product_id, key) unique index, so they stay fast as the catalog grows.
 */
class BrowseQuery
{
    public const SORTS = [
        'best' => 'Best match',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'rating' => 'Rating (review-weighted)',
        'reviews' => 'Most reviewed',
    ];

    public function __construct(private RequirementCatalog $requirements) {}

    public function build(Category $category, array $filters): Builder
    {
        $query = Product::query()
            ->select(['id', 'category_id', 'slug', 'name', 'brand', 'image_url', 'rating', 'review_count', 'best_price', 'best_offer_id', 'offers_count', 'weighted_rating', 'source', 'model_group'])
            ->with('bestOffer:id,store_id,price,mrp,original_currency,last_checked_at', 'bestOffer.store:id,name')
            ->where('category_id', $category->id)
            ->whereNotNull('best_price');

        if (isset($filters['min_price']) && is_numeric($filters['min_price'])) {
            $query->where('best_price', '>=', (float) $filters['min_price']);
        }
        if (isset($filters['max_price']) && is_numeric($filters['max_price'])) {
            $query->where('best_price', '<=', (float) $filters['max_price']);
        }
        if (! empty($filters['brands'])) {
            $query->whereIn('brand', (array) $filters['brands']);
        }
        if (isset($filters['min_rating']) && is_numeric($filters['min_rating'])) {
            $query->where('rating', '>=', (float) $filters['min_rating']);
        }
        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%');
        }

        foreach ((array) ($filters['must'] ?? []) as $key) {
            $requirement = $this->requirements->make($category->slug, (string) $key);
            if ($requirement === null) {
                continue;
            }
            $query->whereExists(function (QueryBuilder $sub) use ($requirement) {
                $sub->select(DB::raw(1))
                    ->from('spec_facts')
                    ->whereColumn('spec_facts.product_id', 'products.id')
                    ->where('spec_facts.key', $requirement->spec)
                    ->where('spec_facts.is_conflict', false);

                match ($requirement->op) {
                    '>=' => $sub->where('num_value', '>=', $requirement->value),
                    '<=' => $sub->where('num_value', '<=', $requirement->value),
                    'true' => $sub->where('bool_value', true),
                    '==' => $sub->where('text_value', $requirement->value),
                    'one_of' => $sub->whereIn('text_value', (array) $requirement->value),
                    'contains' => $sub->where('text_value', 'like', '%'.$requirement->value.'%'),
                };
            });
        }

        return match ($filters['sort'] ?? 'best') {
            'price_asc' => $query->orderBy('best_price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('best_price')->orderBy('id'),
            'rating' => $query->orderByDesc('weighted_rating')->orderBy('best_price'),
            'reviews' => $query->orderByDesc('review_count')->orderBy('id'),
            default => $query->orderByDesc('weighted_rating')->orderBy('best_price')->orderBy('id'),
        };
    }

    /** Filter options for the sidebar, cached for an hour (cleared after each import). */
    public function facets(Category $category): array
    {
        return Cache::remember("facets:{$category->id}", now()->addHour(), function () use ($category) {
            $stats = Product::where('category_id', $category->id)->whereNotNull('best_price')
                ->selectRaw('MIN(best_price) AS min_price, MAX(best_price) AS max_price, COUNT(*) AS total')
                ->first();

            $brands = Product::where('category_id', $category->id)->whereNotNull('brand')
                ->groupBy('brand')->orderBy('brand')
                ->selectRaw('brand, COUNT(*) AS products')
                ->pluck('products', 'brand')->all();

            $musts = [];
            foreach ($this->requirements->definitions($category->slug) as $key => $def) {
                $musts[$key] = $this->requirements->make($category->slug, $key)->label;
            }

            return [
                'min_price' => (float) ($stats->min_price ?? 0),
                'max_price' => (float) ($stats->max_price ?? 0),
                'total' => (int) ($stats->total ?? 0),
                'brands' => $brands,
                'must_haves' => $musts,
                'sorts' => self::SORTS,
            ];
        });
    }
}
