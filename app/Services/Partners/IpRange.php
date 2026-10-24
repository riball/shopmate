<?php

namespace App\Services\Partners;

use InvalidArgumentException;

/** Validates allowlist entries: a single IP (v4 or v6) or a CIDR range like 203.0.113.0/24. */
final class IpRange
{
    public static function isValid(string $entry): bool
    {
        $entry = trim($entry);
        if (filter_var($entry, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (! preg_match('#^([^/]+)/(\d{1,3})$#', $entry, $m)) {
            return false;
        }

        $ip = filter_var($m[1], FILTER_VALIDATE_IP);
        if ($ip === false) {
            return false;
        }

        $maxBits = str_contains($ip, ':') ? 128 : 32;

        return (int) $m[2] <= $maxBits;
    }

    /** Refuses ranges that would let most of the internet in, like 0.0.0.0/0. */
    public static function isTooBroad(string $entry): bool
    {
        if (! str_contains($entry, '/')) {
            return false;
        }
        [$ip, $bits] = explode('/', trim($entry), 2);

        return (int) $bits < (str_contains($ip, ':') ? 32 : 16);
    }

    /**
     * Trims, validates and de-duplicates a list of entries.
     *
     * @param  array<int, string>  $entries
     * @return array<int, string>
     *
     * @throws InvalidArgumentException with a message fit to show the admin
     */
    public static function normalise(array $entries): array
    {
        $clean = [];
        foreach ($entries as $entry) {
            $entry = trim((string) $entry);
            if (! self::isValid($entry)) {
                throw new InvalidArgumentException("\"{$entry}\" is not a valid IP address or CIDR range.");
            }
            if (self::isTooBroad($entry)) {
                throw new InvalidArgumentException("\"{$entry}\" is too broad (it would allow most of the internet).");
            }
            $clean[] = $entry;
        }

        return array_values(array_unique($clean));
    }
}
