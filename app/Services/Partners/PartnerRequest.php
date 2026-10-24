<?php

namespace App\Services\Partners;

use App\Models\Partner;
use Illuminate\Http\Request;
use LogicException;

/** One incoming partner request as it moves through the checks. The first check fills in the partner. */
final class PartnerRequest
{
    public ?Partner $partner = null;

    public function __construct(public readonly Request $http) {}

    public function header(string $name): ?string
    {
        $value = $this->http->headers->get($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function partner(): Partner
    {
        return $this->partner ?? throw new LogicException('Partner not identified yet: ApiKeyCheck must run first.');
    }
}
