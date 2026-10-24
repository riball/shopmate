<?php

namespace App\Services\Partners\Checks;

use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Each partner's own request limit (requests per minute, set per partner),
 * so one misbehaving partner cannot slow the API down for the rest.
 *
 * It lives in the check chain rather than as route middleware because it
 * needs to know which partner is calling, and Laravel runs its throttle
 * middleware before custom middleware like PartnerGuard.
 */
final class RateLimitCheck implements PartnerCheck
{
    public function check(PartnerRequest $request): void
    {
        $partner = $request->partner();
        $key = "partner-rate:{$partner->id}";
        $max = max(1, (int) $partner->rate_limit_per_minute);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $retryAfter = RateLimiter::availableIn($key);

            throw new PartnerRejected(
                'rate_limited', 429, 'rate_limited',
                "Too many requests. Try again in {$retryAfter} seconds.",
                ['Retry-After' => (string) $retryAfter],
            );
        }

        RateLimiter::hit($key, 60);
    }
}
