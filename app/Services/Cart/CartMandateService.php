<?php

namespace App\Services\Cart;

use App\Models\CartMandate;
use App\Models\ProductOffer;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cart Mandate: a mock purchase flow modelled on Google's AP2 agent-payments idea.
 *
 * 1. create():   the shopper ticks consent; the SERVER looks up the price itself
 *                (never trusting the browser) and signs the exact cart.
 * 2. checkout(): refused if the cart changed, the price moved, 15 minutes passed,
 *                or the mandate was already used.
 * Every step is written to the audit log. No real money is involved.
 */
class CartMandateService
{
    public function __construct(private MandateSigner $signer, private AuditLogger $audit) {}

    public function create(ProductOffer $offer, int $quantity, bool $consent, ?string $searchSessionId = null): CartMandate
    {
        if (! $consent) {
            $this->audit->log('mandate.refused_no_consent', 'offer', (string) $offer->id);

            throw new MandateException('Tick the consent box to approve this cart.', 422);
        }

        $offer->loadMissing('product', 'store');
        $quantity = max(1, min($quantity, 5));
        $id = (string) Str::uuid();
        $now = now();
        $expires = $now->copy()->addMinutes((int) config('shopmate.mandate.ttl_minutes', 15));

        $payload = [
            'mandate_id' => $id,
            'product_id' => $offer->product_id,
            'product_name' => $offer->product->name,
            'offer_id' => $offer->id,
            'store' => $offer->store->name,
            'unit_price' => round((float) $offer->price, 2),
            'quantity' => $quantity,
            'total' => round((float) $offer->price * $quantity, 2),
            'currency' => 'INR',
            'consented_at' => $now->toIso8601String(),
            'expires_at' => $expires->toIso8601String(),
        ];

        $mandate = CartMandate::create([
            'id' => $id,
            'search_session_id' => $searchSessionId,
            'product_id' => $offer->product_id,
            'product_offer_id' => $offer->id,
            'quantity' => $quantity,
            'unit_price' => $payload['unit_price'],
            'total' => $payload['total'],
            'payload' => $payload,
            'signature' => $this->signer->sign($payload),
            'status' => 'pending',
            'consented_at' => $now,
            'expires_at' => $expires,
        ]);

        $this->audit->log('mandate.signed', 'mandate', $id, ['total' => $payload['total'], 'offer_id' => $offer->id]);

        return $mandate;
    }

    /**
     * @param  array  $payload  the cart exactly as the browser sends it back
     */
    public function checkout(string $mandateId, array $payload, string $signature): CartMandate
    {
        // Decide inside a transaction, but log and throw after it commits,
        // so a refusal is still recorded in the audit log.
        [$mandate, $refusal] = DB::transaction(function () use ($mandateId, $payload, $signature) {
            // Row lock: two checkouts of the same mandate at the same moment can't both succeed.
            $mandate = CartMandate::whereKey($mandateId)->lockForUpdate()->first();

            $refusal = $this->refusalReason($mandate, $payload, $signature);
            if ($refusal !== null) {
                // A wrong signature proves nothing about the real cart, so it doesn't close the mandate.
                if ($mandate && $mandate->status === 'pending' && ! in_array($refusal[0], ['tampered', 'reused'], true)) {
                    $mandate->update(['status' => $refusal[0] === 'expired' ? 'expired' : 'rejected']);
                }

                return [$mandate, $refusal];
            }

            $mandate->update([
                'status' => 'completed',
                'used_at' => now(),
                'order_ref' => 'MOCK-'.strtoupper(Str::random(8)),
            ]);

            return [$mandate, null];
        });

        if ($refusal !== null) {
            [$event, $message, $status] = $refusal;
            $this->audit->log("checkout.refused.{$event}", 'mandate', $mandateId);

            throw new MandateException($message, $status);
        }

        $this->audit->log('checkout.completed', 'mandate', $mandate->id, ['order_ref' => $mandate->order_ref, 'total' => $mandate->total]);

        return $mandate;
    }

    /** @return array{0: string, 1: string, 2: int}|null [event, message for the shopper, HTTP status] */
    private function refusalReason(?CartMandate $mandate, array $payload, string $signature): ?array
    {
        if (! $mandate) {
            return ['not_found', 'This cart approval does not exist.', 404];
        }
        if ($mandate->used_at !== null || $mandate->status === 'completed') {
            return ['reused', 'This cart approval was already used.', 409];
        }
        if ($mandate->status !== 'pending') {
            return ['closed', 'This cart approval is no longer valid. Start again from the product.', 409];
        }
        if ($mandate->expires_at->isPast()) {
            return ['expired', 'This cart approval expired after 15 minutes. Approve the cart again.', 409];
        }
        if (! $this->signer->verify($payload, $signature) || $this->signer->canonical($payload) !== $this->signer->canonical($mandate->payload)) {
            return ['tampered', 'The cart was changed after it was approved, so checkout was refused.', 422];
        }

        $current = ProductOffer::whereKey($mandate->product_offer_id)->value('price');
        if ($current === null || abs((float) $current - $mandate->unit_price) >= 0.01) {
            return ['price_moved', 'The price changed since you approved the cart (now ₹'.number_format((float) $current).'). Approve the new price to continue.', 409];
        }

        return null;
    }
}
