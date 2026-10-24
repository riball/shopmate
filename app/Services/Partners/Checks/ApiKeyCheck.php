<?php

namespace App\Services\Partners\Checks;

use App\Models\Partner;
use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;

/** Who is calling? Looks up the partner from the X-Partner-Key header. */
final class ApiKeyCheck implements PartnerCheck
{
    public function check(PartnerRequest $request): void
    {
        $key = $request->header('X-Partner-Key');
        if ($key === null) {
            throw PartnerRejected::unauthenticated('missing_key');
        }

        $partner = Partner::with('store')->where('key_id', $key)->first();
        if ($partner === null) {
            throw PartnerRejected::unauthenticated('unknown_key');
        }

        $request->partner = $partner;
    }
}
