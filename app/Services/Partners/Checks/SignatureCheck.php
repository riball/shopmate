<?php

namespace App\Services\Partners\Checks;

use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;
use App\Services\Partners\RequestSigner;
use Illuminate\Support\Facades\Cache;

/**
 * Was the request signed with this partner's secret, recently, and only once?
 *
 * - Timestamp: must be within a few minutes of the server clock.
 * - Signature: HMAC-SHA256 over method, path, timestamp, nonce and body.
 * - Nonce: each one is accepted once (replay protection: a copied request
 *   cannot be sent again). Checked last, so unsigned junk cannot use up nonces.
 */
final class SignatureCheck implements PartnerCheck
{
    public function __construct(private RequestSigner $signer) {}

    public function check(PartnerRequest $request): void
    {
        $timestamp = $request->header('X-Partner-Timestamp');
        $nonce = $request->header('X-Partner-Nonce');
        $signature = $request->header('X-Partner-Signature');

        if ($timestamp === null || $nonce === null || $signature === null) {
            throw PartnerRejected::unauthenticated('missing_signature_headers');
        }
        if (! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce) || ! ctype_xdigit($signature)) {
            throw PartnerRejected::unauthenticated('malformed_signature_headers');
        }

        $skew = (int) config('shopmate.partners.max_clock_skew_seconds');
        if (abs(now()->timestamp - (int) $timestamp) > $skew) {
            throw PartnerRejected::unauthenticated('stale_timestamp');
        }

        $partner = $request->partner();
        $http = $request->http;
        $valid = $this->signer->verify(
            (string) $partner->secret,
            $signature,
            $http->getMethod(),
            $http->getRequestUri(),
            $timestamp,
            $nonce,
            $http->getContent(),
        );
        if (! $valid) {
            throw PartnerRejected::unauthenticated('bad_signature');
        }

        // Cache::add only succeeds if the key is new, and it is atomic, so two copies
        // of the same request sent at once cannot both get through.
        $ttl = $skew * 2 + 60;
        if (! Cache::add("partner-nonce:{$partner->id}:{$nonce}", true, $ttl)) {
            throw PartnerRejected::unauthenticated('replayed_nonce');
        }
    }
}
