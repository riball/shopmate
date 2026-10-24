<?php

namespace App\Services\Partners\Checks;

use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;

/**
 * Is the request coming from one of this partner's registered addresses?
 *
 * Runs before the signature check, so a leaked key and secret are still
 * useless from anywhere else. The IP is the real connecting address:
 * X-Forwarded-For is ignored unless it comes from a proxy listed in
 * TRUSTED_PROXIES (see config/trustedproxy.php), so it cannot be faked.
 */
final class IpAllowlistCheck implements PartnerCheck
{
    public function check(PartnerRequest $request): void
    {
        if (! $request->partner()->allowsIp($request->http->ip())) {
            throw PartnerRejected::unauthenticated('ip_not_allowed');
        }
    }
}
