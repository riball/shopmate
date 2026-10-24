<?php

namespace Tests\Feature;

use App\Enums\PartnerStatus;
use App\Models\AuditLog;
use App\Models\Partner;
use App\Models\PriceHistory;
use App\Models\ProductOffer;
use App\Models\Store;
use App\Services\Partners\RequestSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/partner/v1/offers';

    private const ALLOWED_IP = '203.0.113.10';

    private Partner $partner;

    private string $secret;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importDemoCatalog();

        $this->store = Store::firstWhere('slug', 'cartwala');
        $this->secret = Partner::newSecret();
        $this->partner = Partner::create([
            'name' => 'CartWala feed',
            'store_id' => $this->store->id,
            'key_id' => Partner::newKeyId(),
            'secret' => $this->secret,
            'allowed_ips' => [self::ALLOWED_IP, '198.51.100.0/24'],
            'status' => PartnerStatus::Active,
            'rate_limit_per_minute' => 60,
        ]);
    }

    /** Sends a request signed the way a partner would, with optional tampering. */
    private function partnerCall(string $method, string $body = '', array $override = []): TestResponse
    {
        $timestamp = (string) ($override['timestamp'] ?? now()->timestamp);
        $nonce = $override['nonce'] ?? Str::random(24);
        $signature = (new RequestSigner)->sign($override['secret'] ?? $this->secret, $method, self::URI, $timestamp, $nonce, $override['signed_body'] ?? $body);

        $server = [
            'REMOTE_ADDR' => $override['ip'] ?? self::ALLOWED_IP,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_PARTNER_KEY' => $override['key'] ?? $this->partner->key_id,
            'HTTP_X_PARTNER_TIMESTAMP' => $timestamp,
            'HTTP_X_PARTNER_NONCE' => $nonce,
            'HTTP_X_PARTNER_SIGNATURE' => $signature,
        ] + ($override['server'] ?? []);

        return $this->call($method, self::URI, [], [], [], $server, $body);
    }

    private function ownOffer(): ProductOffer
    {
        return ProductOffer::with('product')->where('store_id', $this->store->id)->orderBy('id')->first();
    }

    private function otherStoresOffer(): ProductOffer
    {
        $soldHere = ProductOffer::where('store_id', $this->store->id)->pluck('product_id');

        return ProductOffer::with('product')->whereNotIn('product_id', $soldHere)->orderBy('id')->firstOrFail();
    }

    private function assertRejectedFor(string $reason): void
    {
        $this->assertTrue(AuditLog::where('event', "partner.rejected.{$reason}")->exists(), "Expected an audit entry for {$reason}.");
    }

    public function test_a_signed_request_from_an_allowed_ip_can_read_its_own_offers(): void
    {
        $response = $this->partnerCall('GET')->assertOk()->assertJsonPath('store', 'cartwala');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
        $this->assertSame(ProductOffer::where('store_id', $this->store->id)->count(), $response->json('meta.total'));
        $this->assertNotNull($this->partner->fresh()->last_used_at);
    }

    public function test_a_price_update_changes_the_price_records_history_and_is_audited(): void
    {
        $offer = $this->ownOffer();
        $newPrice = $offer->price - 500;
        $body = json_encode(['offers' => [['product' => $offer->product->slug, 'price' => $newPrice, 'in_stock' => true]]]);

        $this->partnerCall('PUT', $body)
            ->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('results.0.status', 'updated');

        $this->assertSame((float) $newPrice, $offer->fresh()->price);
        $this->assertTrue(PriceHistory::where('product_offer_id', $offer->id)->where('price', $newPrice)->exists());
        $this->assertLessThanOrEqual($newPrice, $offer->product->fresh()->best_price);
        $this->assertTrue(AuditLog::where('event', 'partner.prices.updated')->exists());
    }

    public function test_a_partner_cannot_change_another_stores_price(): void
    {
        $foreign = $this->otherStoresOffer();
        $before = $foreign->price;

        // Only a product this store does not sell, so nothing of ours is touched either.
        $soldHere = ProductOffer::where('store_id', $this->store->id)->where('product_id', $foreign->product_id)->exists();
        $this->assertFalse($soldHere, 'Test data: pick a product the partner store does not sell.');

        $body = json_encode(['offers' => [['product' => $foreign->product->slug, 'price' => 1]]]);
        $this->partnerCall('PUT', $body)
            ->assertOk()
            ->assertJsonPath('skipped', 1)
            ->assertJsonPath('results.0.reason', 'not_sold_by_your_store');

        $this->assertSame($before, $foreign->fresh()->price);
    }

    public function test_an_unknown_key_is_rejected_with_a_generic_message(): void
    {
        $this->partnerCall('GET', '', ['key' => 'pk_doesnotexist'])
            ->assertStatus(401)
            ->assertJsonPath('error', 'unauthorized')
            ->assertJsonMissingPath('reason');

        $this->assertRejectedFor('unknown_key');
    }

    public function test_a_request_from_an_unlisted_ip_is_rejected_even_with_a_valid_signature(): void
    {
        $this->partnerCall('GET', '', ['ip' => '192.0.2.55'])->assertStatus(401)->assertJsonPath('error', 'unauthorized');

        $this->assertRejectedFor('ip_not_allowed');
    }

    public function test_cidr_ranges_in_the_allowlist_work(): void
    {
        $this->partnerCall('GET', '', ['ip' => '198.51.100.77'])->assertOk();
    }

    public function test_a_faked_x_forwarded_for_header_does_not_bypass_the_allowlist(): void
    {
        $this->partnerCall('GET', '', [
            'ip' => '192.0.2.55',
            'server' => ['HTTP_X_FORWARDED_FOR' => self::ALLOWED_IP],
        ])->assertStatus(401);

        $this->assertRejectedFor('ip_not_allowed');
    }

    public function test_a_changed_body_breaks_the_signature(): void
    {
        $offer = $this->ownOffer();
        $signed = json_encode(['offers' => [['product' => $offer->product->slug, 'price' => 20000]]]);
        $sent = json_encode(['offers' => [['product' => $offer->product->slug, 'price' => 1]]]);

        $this->partnerCall('PUT', $sent, ['signed_body' => $signed])->assertStatus(401);

        $this->assertRejectedFor('bad_signature');
        $this->assertNotSame(1.0, $offer->fresh()->price);
    }

    public function test_a_wrong_secret_is_rejected(): void
    {
        $this->partnerCall('GET', '', ['secret' => Partner::newSecret()])->assertStatus(401);

        $this->assertRejectedFor('bad_signature');
    }

    public function test_an_old_timestamp_is_rejected(): void
    {
        $this->partnerCall('GET', '', ['timestamp' => now()->subMinutes(10)->timestamp])->assertStatus(401);

        $this->assertRejectedFor('stale_timestamp');
    }

    public function test_a_replayed_request_is_rejected(): void
    {
        $nonce = Str::random(24);

        $this->partnerCall('GET', '', ['nonce' => $nonce])->assertOk();
        $this->partnerCall('GET', '', ['nonce' => $nonce])->assertStatus(401);

        $this->assertRejectedFor('replayed_nonce');
    }

    public function test_read_only_partners_can_read_but_not_write(): void
    {
        $this->partner->update(['status' => PartnerStatus::ReadOnly]);
        $offer = $this->ownOffer();
        $before = $offer->price;

        $this->partnerCall('GET')->assertOk();
        $this->partnerCall('PUT', json_encode(['offers' => [['product' => $offer->product->slug, 'price' => 99]]]))
            ->assertStatus(403)
            ->assertJsonPath('error', 'partner_read_only');

        $this->assertSame($before, $offer->fresh()->price);
    }

    public function test_a_suspended_partner_is_cut_off_without_affecting_others(): void
    {
        $other = Partner::create([
            'name' => 'Other feed',
            'store_id' => Store::firstWhere('slug', 'dealdeck')->id,
            'key_id' => Partner::newKeyId(),
            'secret' => $otherSecret = Partner::newSecret(),
            'allowed_ips' => [self::ALLOWED_IP],
        ]);
        $this->partner->update(['status' => PartnerStatus::Suspended]);

        $this->partnerCall('GET')->assertStatus(403)->assertJsonPath('error', 'partner_suspended');
        $this->partnerCall('GET', '', ['key' => $other->key_id, 'secret' => $otherSecret])->assertOk()->assertJsonPath('store', 'dealdeck');
    }

    public function test_each_partner_has_its_own_rate_limit(): void
    {
        $this->partner->update(['rate_limit_per_minute' => 2]);

        $this->partnerCall('GET')->assertOk();
        $this->partnerCall('GET')->assertOk();
        $this->partnerCall('GET')->assertStatus(429);
    }

    public function test_an_ip_is_locked_out_after_repeated_failures_but_successes_never_count(): void
    {
        config(['shopmate.partners.max_failed_attempts_per_minute' => 3]);

        // Plenty of successful requests from the partner's own IP: no lockout.
        for ($i = 0; $i < 5; $i++) {
            $this->partnerCall('GET')->assertOk();
        }

        // An attacker guessing keys from another address is stopped after 3 failures,
        // and then refused even with valid credentials until the minute is up.
        $attacker = '198.51.100.200';
        for ($i = 0; $i < 3; $i++) {
            $this->partnerCall('GET', '', ['ip' => $attacker, 'key' => 'pk_guess'.$i])->assertStatus(401);
        }
        $this->partnerCall('GET', '', ['ip' => $attacker])->assertStatus(429)->assertJsonPath('error', 'too_many_failed_attempts');

        $this->travel(61)->seconds();
        $this->partnerCall('GET', '', ['ip' => $attacker])->assertOk();
    }

    public function test_invalid_updates_are_refused_before_touching_prices(): void
    {
        $offer = $this->ownOffer();
        $body = json_encode(['offers' => [['product' => $offer->product->slug, 'price' => -5]]]);

        $this->partnerCall('PUT', $body)->assertStatus(422);
    }

    public function test_the_admin_kill_switch_changes_status_and_is_audited(): void
    {
        $this->withSession(['is_admin' => true])
            ->get('/admin/partners')
            ->assertOk()
            ->assertSee('CartWala feed');

        $this->withSession(['is_admin' => true])
            ->post("/admin/partners/{$this->partner->id}/status", ['status' => 'suspended'])
            ->assertRedirect();

        $this->assertSame(PartnerStatus::Suspended, $this->partner->fresh()->status);
        $this->assertTrue(AuditLog::where('event', 'partner.status.changed')->exists());
    }

    public function test_guests_cannot_use_the_kill_switch(): void
    {
        $this->post("/admin/partners/{$this->partner->id}/status", ['status' => 'suspended'])->assertRedirect('/admin/login');

        $this->assertSame(PartnerStatus::Active, $this->partner->fresh()->status);
    }

    public function test_the_secret_is_stored_encrypted_and_never_serialised(): void
    {
        $raw = DB::table('partners')->where('id', $this->partner->id)->value('secret');

        $this->assertNotSame($this->secret, $raw);
        $this->assertArrayNotHasKey('secret', $this->partner->fresh()->toArray());
    }
}
