<?php

namespace App\Http\Middleware;

use App\Services\Partners\Checks\PartnerCheck;
use App\Services\Partners\PartnerAudit;
use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the partner API. Runs every check listed in
 * config('shopmate.partners.checks'), in order. The first failure stops the
 * request, is written to the audit log with the exact reason, and returns a
 * response carrying a request ID that support can look up in the log.
 *
 * Brute-force lockout: an IP that fails authentication too often in a minute
 * is refused before any check runs, so keys and signatures cannot be guessed
 * at speed. Successful requests never count towards it.
 */
class PartnerGuard
{
    public function __construct(private PartnerAudit $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::ulid();
        $partnerRequest = new PartnerRequest($request);

        $lockoutKey = 'partner-failed:'.$request->ip();
        $maxFailures = (int) config('shopmate.partners.max_failed_attempts_per_minute');
        if (RateLimiter::tooManyAttempts($lockoutKey, $maxFailures)) {
            $retryAfter = RateLimiter::availableIn($lockoutKey);

            return response()->json([
                'error' => 'too_many_failed_attempts',
                'message' => "Too many failed attempts from this address. Try again in {$retryAfter} seconds.",
                'request_id' => $requestId,
            ], 429, ['X-Request-Id' => $requestId, 'Retry-After' => (string) $retryAfter]);
        }

        try {
            foreach (config('shopmate.partners.checks') as $checkClass) {
                $check = app($checkClass);
                if (! $check instanceof PartnerCheck) {
                    throw new LogicException("{$checkClass} must implement PartnerCheck.");
                }
                $check->check($partnerRequest);
            }
        } catch (PartnerRejected $rejected) {
            if ($rejected->status === 401) {
                RateLimiter::hit($lockoutKey, 60);
            }
            if ($this->shouldAudit($rejected, $partnerRequest)) {
                $this->audit->rejected($request, $rejected, $partnerRequest->partner, $requestId);
            }

            return response()->json([
                'error' => $rejected->publicError,
                'message' => $rejected->getMessage(),
                'request_id' => $requestId,
            ], $rejected->status, ['X-Request-Id' => $requestId] + $rejected->headers);
        }

        $partner = $partnerRequest->partner();
        $partner->forceFill(['last_used_at' => now()])->saveQuietly();
        $request->attributes->set('partner', $partner);
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    /** A partner hammering the API should not flood the audit log: note rate limiting once a minute. */
    private function shouldAudit(PartnerRejected $rejected, PartnerRequest $request): bool
    {
        if ($rejected->reason !== 'rate_limited' || $request->partner === null) {
            return true;
        }

        return Cache::add("partner-rate-logged:{$request->partner->id}", true, 60);
    }
}
