<?php

namespace Tests\Feature;

use App\Mail\PriceDropMail;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\ProductOffer;
use Database\Seeders\PageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PageSeeder::class);
        $this->importDemoCatalog();
    }

    public function test_public_pages_render(): void
    {
        $product = Product::first();

        $this->get('/')->assertOk()->assertSee('What are you shopping for?');
        $this->get('/c/smartphones')->assertOk()->assertSee('Smartphones');
        $this->get('/p/'.$product->slug)->assertOk()->assertSee($product->name)->assertSee('application/ld+json', false);
        $this->get('/pages/how-it-works')->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee($product->slug);
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
        $this->get('/health')->assertOk()->assertJsonPath('status', 'ok');
    }

    public function test_browse_filters_use_spec_facts(): void
    {
        $res = $this->getJson('/api/products?category=smartphones&must[]=5g&max_price=20000&sort=price_asc')->assertOk();

        $prices = collect($res->json('products'))->pluck('price');
        $this->assertNotEmpty($prices);
        $this->assertTrue($prices->every(fn ($p) => $p <= 20000));
        $this->assertSame($prices->sort()->values()->all(), $prices->all());
        $this->assertFalse(collect($res->json('products'))->pluck('name')->contains('Kairo K2'), 'Kairo K2 is not 5G');
    }

    public function test_price_alert_fires_once(): void
    {
        Mail::fake();
        $product = Product::first();

        $this->postJson('/api/price-alerts', ['product_id' => $product->id, 'email' => 'a@example.com', 'target_price' => $product->best_price - 100])
            ->assertCreated();

        $this->artisan('alerts:check')->assertSuccessful();
        Mail::assertNothingSent();

        ProductOffer::where('product_id', $product->id)->update(['price' => $product->best_price - 200]);
        $product->refreshBestOffer();

        $this->artisan('alerts:check')->assertSuccessful();
        $this->artisan('alerts:check')->assertSuccessful();
        Mail::assertSent(PriceDropMail::class, 1);
        $this->assertNotNull(PriceAlert::first()->notified_at);
    }

    public function test_admin_can_edit_seo_with_the_password(): void
    {
        config(['shopmate.admin_password' => 'secret-pass']);
        $product = Product::first();

        $this->get('/admin/seo')->assertRedirect('/admin/login');
        $this->post('/admin/login', ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->post('/admin/login', ['password' => 'secret-pass'])->assertRedirect();

        $this->put("/admin/seo/product/{$product->id}", ['title' => 'Custom title', 'description' => 'Custom description', 'action' => 'save'])
            ->assertRedirect();

        $this->get('/p/'.$product->slug)->assertSee('<title>Custom title</title>', false);
    }

    public function test_dummyjson_import_converts_prices_with_frankfurter(): void
    {
        Http::fake([
            '*frankfurter*' => Http::response(['rates' => ['INR' => 88.0]]),
            '*dummyjson.com/products/category/smartphones*' => Http::response(['products' => [
                ['id' => 1, 'title' => 'Demo Phone', 'brand' => 'Demo', 'price' => 100, 'discountPercentage' => 10, 'rating' => 4.2, 'reviews' => [[], []], 'stock' => 5],
            ]]),
            '*' => Http::response(['products' => []]),
        ]);

        $this->artisan('products:import', ['--source' => 'dummyjson'])->assertSuccessful();

        $offer = Product::where('source', 'dummyjson')->firstOrFail()->offers()->first();
        $this->assertSame(7920.0, $offer->price);   // $90 × 88
        $this->assertSame('USD', $offer->original_currency);
        $this->assertSame(88.0, $offer->fx_rate);
    }
}
