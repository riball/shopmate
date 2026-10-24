<?php

namespace App\Services\Partners\Checks;

use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;

/**
 * The kill switch. Runs after the partner has proven who it is, so only the
 * real partner learns that it has been suspended or set to read-only.
 */
final class PartnerStatusCheck implements PartnerCheck
{
    public function check(PartnerRequest $request): void
    {
        $status = $request->partner()->status;

        if (! $status->allowsReads()) {
            throw new PartnerRejected('partner_suspended', 403, 'partner_suspended', 'This partner account is suspended. Contact ShopMate support.');
        }

        if (! $request->http->isMethodSafe() && ! $status->allowsWrites()) {
            throw new PartnerRejected('partner_read_only', 403, 'partner_read_only', 'This partner account is read-only right now. Price updates are paused.');
        }
    }
}
