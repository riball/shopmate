<?php

namespace App\Services\Partners;

/**
 * How a partner request is signed (HMAC-SHA256), shared by the server check,
 * the tests and the demo script, so all three agree on exactly one format.
 *
 * The signed text is five lines:
 *   METHOD
 *   PATH (with the query string, exactly as sent)
 *   TIMESTAMP (Unix seconds)
 *   NONCE (a random one-time value)
 *   SHA-256 of the raw request body (hex; of the empty string when there is no body)
 *
 * Changing any of them (the price in the body, the URL, the time) breaks the signature.
 */
final class RequestSigner
{
    public function canonical(string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);
    }

    public function sign(string $secret, string $method, string $path, string $timestamp, string $nonce, string $body): string
    {
        return hash_hmac('sha256', $this->canonical($method, $path, $timestamp, $nonce, $body), $secret);
    }

    /** Constant-time comparison, so timing reveals nothing about the right signature. */
    public function verify(string $secret, string $signature, string $method, string $path, string $timestamp, string $nonce, string $body): bool
    {
        return hash_equals($this->sign($secret, $method, $path, $timestamp, $nonce, $body), strtolower($signature));
    }
}
