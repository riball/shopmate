<?php

namespace App\Services\Cart;

/**
 * Signs a cart with HMAC-SHA256 (a tamper-proof stamp made with a secret key
 * only the server knows). If anyone changes even one character of the cart,
 * the signature no longer matches.
 */
class MandateSigner
{
    public function __construct(private ?string $secret = null)
    {
        $this->secret ??= (string) config('shopmate.mandate.secret');
    }

    public function sign(array $payload): string
    {
        return hash_hmac('sha256', $this->canonical($payload), $this->secret);
    }

    /** Constant-time comparison, so the check itself leaks nothing about the signature. */
    public function verify(array $payload, string $signature): bool
    {
        return hash_equals($this->sign($payload), $signature);
    }

    /**
     * Same data always gives the same text: keys sorted, and 1799.0 written as 1799,
     * so a cart that travels through the browser as JSON still matches exactly.
     */
    public function canonical(array $payload): string
    {
        $normalize = function (array $data) use (&$normalize): array {
            ksort($data);
            foreach ($data as $key => $value) {
                $data[$key] = match (true) {
                    is_array($value) => $normalize($value),
                    is_float($value) && floor($value) === $value => (int) $value,
                    is_float($value) => round($value, 2),
                    default => $value,
                };
            }

            return $data;
        };

        return json_encode($normalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
