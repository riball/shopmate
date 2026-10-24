<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\SeoMeta;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** sitemap.xml for search engines, skipping anything marked "noindex" in the SEO admin. */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHours(6), function () {
            $hidden = SeoMeta::where('noindex', true)->get(['seoable_type', 'seoable_id'])
                ->map(fn ($m) => "{$m->seoable_type}:{$m->seoable_id}")->flip();

            $urls = [];
            foreach (Page::all() as $page) {
                if (! isset($hidden["page:{$page->id}"])) {
                    $urls[] = [$page->publicUrl(), $page->updated_at];
                }
            }
            foreach (Category::all() as $category) {
                if (! isset($hidden["category:{$category->id}"])) {
                    $urls[] = [$category->publicUrl(), $category->updated_at];
                }
            }
            Product::whereNotNull('best_price')->select(['id', 'slug', 'updated_at'])->chunkById(500, function ($products) use (&$urls, $hidden) {
                foreach ($products as $product) {
                    if (! isset($hidden["product:{$product->id}"])) {
                        $urls[] = [route('products.show', $product->slug), $product->updated_at];
                    }
                }
            });

            // The XML declaration is added here, not in the Blade file: with PHP's
            // short_open_tag setting switched on, "<?xml" inside a template is read as PHP code.
            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}