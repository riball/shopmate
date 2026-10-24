<?php

namespace App\Services\Catalog;

use Illuminate\Support\Str;

/**
 * Works out a "model group" so colour and storage variants of the same
 * product collapse into one result:
 *   "Vantro V5 (Midnight Blue, 128 GB)" and "Vantro V5 (Silver, 256 GB)" -> "vantro-v5"
 */
class ModelGrouper
{
    private const COLOURS = [
        'black', 'white', 'blue', 'red', 'green', 'grey', 'gray', 'silver', 'gold', 'pink', 'purple',
        'yellow', 'orange', 'beige', 'navy', 'midnight', 'graphite', 'ivory', 'teal', 'olive', 'charcoal',
    ];

    public function group(string $name): string
    {
        $base = mb_strtolower($name);
        $base = preg_replace('/\(.*?\)|\[.*?\]/', ' ', $base);                 // "(Black, 128 GB)"
        $base = preg_replace('/\b\d+\s*(gb|tb)(\s*ram)?\b/', ' ', $base);        // "8GB RAM", "128 GB"
        $base = preg_replace('/\b('.implode('|', self::COLOURS).')\b/', ' ', $base);
        $base = preg_replace('/\s*[,|-]\s*$/', '', trim(preg_replace('/\s+/', ' ', $base)));

        return Str::slug($base) ?: Str::slug($name);
    }
}
