<?php

namespace App\Support;

/** Human-readable spec names: "ram" -> "RAM", "5g" -> "5G", "battery life" -> "Battery life". */
final class SpecLabel
{
    private const ACRONYMS = ['ram' => 'RAM', 'rom' => 'ROM', '5g' => '5G', 'gps' => 'GPS', 'spo2' => 'SpO2', 'ssd' => 'SSD', 'hdd' => 'HDD', 'os' => 'OS', 'anc' => 'ANC', 'cpu' => 'CPU', 'ip' => 'IP'];

    public static function for(string $category, string $key): string
    {
        $alias = config("shopmate_specs.{$category}.specs.{$key}.1.0") ?? str_replace('_', ' ', $key);
        $words = array_map(fn ($w) => self::ACRONYMS[strtolower($w)] ?? $w, explode(' ', $alias));

        return ucfirst(implode(' ', $words));
    }
}
