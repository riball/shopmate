<?php

namespace App\Services\Pipeline;

/**
 * Reads a rupee budget out of plain text, exactly (no AI involved):
 *   "under ₹2,000" -> 2000     "below 20k" -> 20000     "1.5 lakh" -> 150000
 *   "budget rs 45000" -> 45000 "around 30 thousand" -> 30000
 * Numbers with product units ("8 GB", "5000 mAh", "50MP") are ignored.
 */
class BudgetParser
{
    private const AMOUNT = '(\d{1,3}(?:,\d{2,3})+|\d+(?:\.\d+)?)\s*(k|thousand|lakh|lakhs|lac|lacs|l|cr|crore)?\b';

    private const PRODUCT_UNITS = '/^\s*(gb|tb|mb|mah|mp|w|hz|hrs?|hours?|days?|kg|g|gm|grams?|inch|inches|"|cm|mm|nits|ram|rom|v|ah)\b/i';

    public function parse(string $text): ?float
    {
        $text = mb_strtolower($text);
        // A currency marker or budget word directly before the amount, e.g. "₹2,000", "under 20k".
        $prefix = '(?:₹|rs\.?|inr|under|below|within|budget(?: of| is)?|less than|upto|up to|max(?:imum)?|around|about|not more than|cheaper than)\s*(?:₹|rs\.?|inr)?\s*';

        if (preg_match_all('/'.$prefix.self::AMOUNT.'/u', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $m) {
                $after = substr($text, $m[0][1] + strlen($m[0][0]));   // offsets are in bytes
                if (empty($m[2][0]) && preg_match(self::PRODUCT_UNITS, $after)) {
                    continue;
                }

                return $this->toRupees($m[1][0], $m[2][0] ?? '');
            }
        }

        // No marker, but a clear money suffix: "20k phone", "1.5 lakh laptop".
        if (preg_match('/\b'.self::AMOUNT.'/u', $text, $m) && ! empty($m[2]) && $m[2] !== 'l') {
            return $this->toRupees($m[1], $m[2]);
        }

        return null;
    }

    private function toRupees(string $number, string $suffix): float
    {
        $value = (float) str_replace(',', '', $number);

        return round(match ($suffix) {
            'k', 'thousand' => $value * 1_000,
            'lakh', 'lakhs', 'lac', 'lacs', 'l' => $value * 100_000,
            'cr', 'crore' => $value * 10_000_000,
            default => $value,
        });
    }
}
