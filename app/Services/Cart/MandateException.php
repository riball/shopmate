<?php

namespace App\Services\Cart;

use RuntimeException;

/** A checkout or approval that must be refused, with the HTTP status to answer with. */
class MandateException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }
}
