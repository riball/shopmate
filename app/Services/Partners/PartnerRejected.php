<?php

namespace App\Services\Partners;

use RuntimeException;

/**
 * Thrown by a check to stop a request.
 *
 * $reason is precise and goes only to the audit log. Callers who have not
 * proven who they are get a generic "unauthorized", so an attacker cannot
 * learn which part (key, IP, signature) was wrong.
 */
final class PartnerRejected extends RuntimeException
{
    /** @param  array<string, string>  $headers  extra response headers, e.g. Retry-After */
    public function __construct(
        public readonly string $reason,
        public readonly int $status = 401,
        public readonly string $publicError = 'unauthorized',
        string $publicMessage = 'Request could not be authenticated.',
        public readonly array $headers = [],
    ) {
        parent::__construct($publicMessage);
    }

    public static function unauthenticated(string $reason): self
    {
        return new self($reason);
    }
}
