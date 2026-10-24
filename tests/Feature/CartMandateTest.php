<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CartMandate;
use App\Models\ProductOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartMandateTest extends TestCase
{
    use RefreshDatabase;

    private ProductOffer $offer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importDemoCatalog();
        $this->offer = ProductOffer::orderBy('id')->first();
    }

    private function approve(array $extra = []): array
    {
        return $this->postJson('/api/cart-mandates', ['offer_id' => $this->offer->id, 'quantity' => 2, 'consent' => true] + $extra)
            ->assertCreated()
            ->json();
    }

    public function test_happy_path_signs_and_checks_out_once(): void
    {
        $m = $this->approve();
        $this->assertSame($this->offer->price * 2, (float) $m['payload']['total']);

        $this->postJson("/api/cart-mandates/{$m['mandate_id']}/checkout", ['payload' => $m['payload'], 'signature' => $m['signature']])
            ->assertOk()
            ->assertJsonPath('message', 'Mock order placed. No money was charged.');

        // Used once only.
        $this->postJson("/api/cart-mandates/{$m['mandate_id']}/checkout", ['payload' => $m['payload'], 'signature' => $m['signature']])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This cart approval was already used.');

        $this->assertTrue(AuditLog::where('event', 'checkout.completed')->exists());
        $this->assertTrue(AuditLog::where('event', 'checkout.refused.reused')->exists());
    }

    public function test_consent_is_required(): void
    {
        $this->postJson('/api/cart-mandates', ['offer_id' => $this->offer->id, 'consent' => false])->assertStatus(422);
        $this->assertSame(0, CartMandate::count());
    }

    public function test_the_browser_cannot_set_the_price(): void
    {
        $m = $this->approve(['price' => 1, 'unit_price' => 1]);
        $this->assertSame($this->offer->price, (float) $m['payload']['unit_price']);
    }

    public function test_a_changed_cart_is_refused(): void
    {
        $m = $this->approve();
        $tampered = array_merge($m['payload'], ['total' => 1]);

        $this->postJson("/api/cart-mandates/{$m['mandate_id']}/checkout", ['payload' => $tampered, 'signature' => $m['signature']])
            ->assertStatus(422);
        $this->assertTrue(AuditLog::where('event', 'checkout.refused.tampered')->exists());
    }

    public function test_a_price_change_is_refused(): void
    {
        $m = $this->approve();
        $this->offer->update(['price' => $this->offer->price + 100]);

        $this->postJson("/api/cart-mandates/{$m['mandate_id']}/checkout", ['payload' => $m['payload'], 'signature' => $m['signature']])
            ->assertStatus(409);
        $this->assertSame('rejected', CartMandate::find($m['mandate_id'])->status);
    }

    public function test_an_expired_cart_is_refused(): void
    {
        $m = $this->approve();
        $this->travel(16)->minutes();

        $this->postJson("/api/cart-mandates/{$m['mandate_id']}/checkout", ['payload' => $m['payload'], 'signature' => $m['signature']])
            ->assertStatus(409);
        $this->assertSame('expired', CartMandate::find($m['mandate_id'])->status);
    }
}
