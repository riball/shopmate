<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Currency conversion through the free Frankfurter API (European Central Bank rates).
 * Rates are cached for 12 hours so imports don't call the API once per product.
 * Converted prices are always labelled "approx." with the rate used.
 */
class FrankfurterClient
{
    public function rate(string $from, string $to = 'INR'): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return 1.0;
        }

        return Cache::remember("fx:{$from}:{$to}", now()->addHours(12), function () use ($from, $to) {
            $response = Http::baseUrl(config('shopmate.sources.frankfurter_url'))
                ->timeout(15)
                ->retry(2, 500, throw: false)
                ->get('/latest', ['base' => $from, 'symbols' => $to]);

            $rate = $response->json("rates.{$to}");
            if (! $response->successful() || ! is_numeric($rate)) {
                throw new RuntimeException("Could not get a {$from}->{$to} exchange rate (HTTP {$response->status()}).");
            }

            return (float) $rate;
        });
    }
}
