<?php

namespace App\Services\Catalog;

/**
 * Turns messy listing spec text into typed facts.
 *   "RAM: 8 GB"          -> ram_gb = 8 (number)
 *   "With Microphone: Yes" -> has_mic = true (bool)
 * Rules (which labels map to which fact) live in config/shopmate_specs.php.
 *
 * If two labels for the same fact disagree, or one value says both yes and no,
 * the fact is marked as a conflict and no value is stored. We never guess.
 */
class SpecNormalizer
{
    /**
     * @param  array<string, mixed>  $rawSpecs  label => value as found in the listing
     * @return array<string, array{num: ?float, text: ?string, bool: ?bool, raw_label: string, raw_value: string, conflict: bool}>
     */
    public function normalize(string $categorySlug, array $rawSpecs): array
    {
        $definitions = config("shopmate_specs.{$categorySlug}.specs", []);
        $aliasToKey = [];
        foreach ($definitions as $key => [$type, $aliases]) {
            foreach ($aliases as $alias) {
                $aliasToKey[$this->cleanLabel($alias)] ??= $key;
            }
        }

        $facts = [];
        foreach ($rawSpecs as $label => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }
            $value = trim((string) $value);
            $key = $aliasToKey[$this->cleanLabel((string) $label)] ?? null;
            if ($key === null || $value === '') {
                continue;
            }

            $type = $definitions[$key][0];
            $parsed = $this->parse($key, $type, $value);
            if ($parsed === null) {
                continue;
            }

            $fact = [
                'num' => $parsed['num'] ?? null,
                'text' => $parsed['text'] ?? null,
                'bool' => $parsed['bool'] ?? null,
                'raw_label' => (string) $label,
                'raw_value' => mb_substr($value, 0, 250),
                'conflict' => $parsed['conflict'] ?? false,
            ];

            if (isset($facts[$key]) && ! $facts[$key]['conflict'] && ! $this->sameValue($facts[$key], $fact)) {
                $fact = $this->conflict($label, $facts[$key]['raw_value'].' | '.$value);
            }
            if (isset($facts[$key]) && $facts[$key]['conflict']) {
                continue;
            }

            $facts[$key] = $fact;
        }

        return $facts;
    }

    private function parse(string $key, string $type, string $value): ?array
    {
        $lower = mb_strtolower($value);

        return match ($type) {
            'number' => $this->parseNumber($key, $lower),
            'bool' => $this->parseBool($lower),
            'gender' => $this->parseGender($lower),
            default => ['text' => mb_substr($lower, 0, 250)],
        };
    }

    private function parseNumber(string $key, string $value): ?array
    {
        if (! preg_match('/(\d{1,3}(?:,\d{2,3})+|\d+(?:\.\d+)?)/', $value, $m)) {
            return null;
        }
        $number = (float) str_replace(',', '', $m[1]);

        // Unit fixes so every fact uses one unit.
        if (str_ends_with($key, '_gb') && preg_match('/\d\s*tb\b/', $value)) {
            $number *= 1024;
        }
        if ($key === 'weight_kg' && preg_match('/\d\s*(g|gm|grams?)\b/', $value) && ! str_contains($value, 'kg')) {
            $number /= 1000;
        }
        if ($key === 'weight_g' && str_contains($value, 'kg')) {
            $number *= 1000;
        }
        if ($key === 'battery_days' && preg_match('/\d\s*(hours?|hrs?)\b/', $value)) {
            $number /= 24;
        }

        return ['num' => round($number, 3)];
    }

    private function parseBool(string $value): ?array
    {
        $no = (bool) preg_match('/\b(no|false|not available|not supported|none|absent)\b/', $value);
        // Remove "not available" first so its "available" doesn't also count as a yes.
        $withoutNegatives = preg_replace('/\bnot (available|supported|present)\b/', ' ', $value);
        $yes = (bool) preg_match('/\b(yes|true|available|supported|present|built[- ]?in)\b/', $withoutNegatives);

        return match (true) {
            $yes && $no => ['conflict' => true],
            $yes => ['bool' => true],
            $no => ['bool' => false],
            default => null,
        };
    }

    private function parseGender(string $value): ?array
    {
        $women = (bool) preg_match('/\b(women|womens|women\'s|girls|female)\b/', $value);
        $men = (bool) preg_match('/\b(men|mens|men\'s|boys|male)\b/', $value);

        return match (true) {
            str_contains($value, 'unisex') || ($men && $women) => ['text' => 'unisex'],
            $women => ['text' => 'women'],
            $men => ['text' => 'men'],
            default => null,
        };
    }

    private function sameValue(array $a, array $b): bool
    {
        if ($a['num'] !== null || $b['num'] !== null) {
            return $a['num'] !== null && $b['num'] !== null && abs($a['num'] - $b['num']) < 0.001;
        }

        return $a['bool'] === $b['bool'] && $a['text'] === $b['text'];
    }

    private function conflict(string $label, string $raw): array
    {
        return [
            'num' => null, 'text' => null, 'bool' => null,
            'raw_label' => $label, 'raw_value' => mb_substr($raw, 0, 250), 'conflict' => true,
        ];
    }

    private function cleanLabel(string $label): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower(rtrim($label, ': '))));
    }
}
