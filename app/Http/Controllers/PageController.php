<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SearchSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** Public pages: home, search results, static pages. */
class PageController extends Controller
{
    public function home(): View
    {
        $page = Page::with('seo')->firstWhere('slug', 'home');

        // Category tiles with product counts, cached as plain arrays: the home page is the busiest page.
        $categories = Cache::remember('home:categories', now()->addMinutes(30), fn () => Category::query()
            ->withCount(['products' => fn ($q) => $q->whereNotNull('best_price')])
            ->withMin('products as from_price', 'best_price')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'slug' => $c->slug,
                'name' => $c->name,
                'products_count' => (int) $c->products_count,
                'from_price' => $c->from_price !== null ? (float) $c->from_price : null,
            ])
            ->all());

        return view('pages.home', [
            'categories' => $categories,
            'seo' => $page?->seoData(),
            'canonical' => route('home'),
            'examples' => [
                'True wireless earbuds under ₹2,000 with a good mic',
                '5G phone under 20k with an AMOLED screen and 8 GB RAM',
                'Lightweight laptop with 16 GB RAM under 60k',
                'Smartwatch with GPS and SpO2 under ₹5,000',
                'Something for my dad\'s birthday',
            ],
        ]);
    }

    public function search(SearchSession $session): View
    {
        return view('pages.search', [
            'session' => $session,
            'seo' => ['title' => 'Search: '.str($session->query)->limit(50).' | ShopMate', 'description' => 'ShopMate search results', 'keywords' => null, 'noindex' => true],
        ]);
    }

    public function show(string $slug): View
    {
        $page = Page::with('seo')->where('slug', $slug)->where('slug', '!=', 'home')->firstOrFail();

        return view('pages.static', [
            'page' => $page,
            'seo' => $page->seoData(),
            'canonical' => $page->publicUrl(),
        ]);
    }

    public function product(Product $product): View
    {
        $product->load(['category', 'specFacts', 'seo', 'offers' => fn ($q) => $q->with('store')->orderBy('price')]);

        return view('pages.product', [
            'product' => $product,
            'seo' => $product->seoData(),
            'canonical' => $product->publicUrl(),
        ]);
    }

    public function browse(Category $category): View
    {
        $category->load('seo');

        return view('pages.browse', [
            'category' => $category,
            'seo' => $category->seoData(),
            'canonical' => $category->publicUrl(),
        ]);
    }
}
