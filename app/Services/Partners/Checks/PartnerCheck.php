<?php

namespace App\Services\Partners\Checks;

use App\Services\Partners\PartnerRejected;
use App\Services\Partners\PartnerRequest;

/**
 * One security check on a partner request. Each check does one job and
 * either passes silently or throws PartnerRejected. The order is set in
 * config/shopmate.php (partners.checks); adding a check means writing one
 * class and adding it to that list.
 */
interface PartnerCheck
{
    /** @throws PartnerRejected */
    public function check(PartnerRequest $request): void;
}
