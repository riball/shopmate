<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\ProductOffer;
use App\Services\Catalog\BrowseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /** Filtered, sorted, paginated product list for the browse page. */
    public function index(Request $request, BrowseQuery $browse): JsonResponse
    {
        $filters = $request->validate([
            'category' => ['required', 'string', 'exists:categories,slug'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'brands' => ['nullable', 'array'],
            'brands.*' => ['string', 'max:80'],
            'must' => ['nullable', 'array'],
            'must.*' => ['string', 'max:60'],
            'min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'q' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', Rule::in(array_keys(BrowseQuery::SORTS))],
        ]);

        $category = Category::where('slug', $filters['category'])->firstOrFail();
        $page = $browse->build($category, $filters)->paginate(12)->withQueryString();

        return response()->json([
            'total' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'products' => collect($page->items())->map(fn (Product $p) => [
                'name' => $p->name,
                'brand' => $p->brand,
                'url' => route('products.show', $p->slug),
                'image_url' => $p->image_url,
                'rating' => $p->rating,
                'review_count' => $p->review_count,
                'weighted_rating' => $p->weighted_rating,
                'price' => $p->best_price,
                'mrp' => $p->bestOffer?->mrp,
                'approx' => $p->bestOffer?->isConverted() ?? false,
                'store' => $p->bestOffer?->store?->name,
                'offers_count' => $p->offers_count,
                'is_demo' => $p->isDemo(),
            ]),
        ]);
    }

    /** Price history per store, for the chart on the product page. */
    public function history(Product $product): JsonResponse
    {
        $offers = ProductOffer::where('product_id', $product->id)->with('store:id,name')->get(['id', 'store_id', 'price']);

        $history = PriceHistory::whereIn('product_offer_id', $offers->pluck('id'))
            ->where('recorded_at', '>=', now()->subDays(90))
            ->orderBy('recorded_at')
            ->get(['product_offer_id', 'price', 'recorded_at'])
            ->groupBy('product_offer_id');

        return response()->json([
            'series' => $offers->map(fn (ProductOffer $o) => [
                'store' => $o->store->name,
                'current' => $o->price,
                'points' => ($history[$o->id] ?? collect())->map(fn ($h) => [
                    'date' => $h->recorded_at->toDateString(),
                    'price' => $h->price,
                ])->values(),
            ])->values(),
        ]);
    }
}
