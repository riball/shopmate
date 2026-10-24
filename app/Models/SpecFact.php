<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecFact extends Model
{
    protected $fillable = [
        'product_id', 'key', 'num_value', 'text_value', 'bool_value',
        'raw_label', 'raw_value', 'is_conflict',
    ];

    protected function casts(): array
    {
        return [
            'num_value' => 'float',
            'bool_value' => 'boolean',
            'is_conflict' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The typed value, whichever column holds it. */
    public function value(): float|bool|string|null
    {
        return $this->num_value ?? $this->bool_value ?? $this->text_value;
    }

    public function display(): string
    {
        if ($this->is_conflict) {
            return 'Listing contradicts itself';
        }
        $value = $this->value();

        // Prefer the listing's own wording ("40 hrs", "1 TB"), which keeps the unit.
        return match (true) {
            $this->raw_value !== null && $this->raw_value !== '' => $this->raw_value,
            is_bool($value) => $value ? 'Yes' : 'No',
            is_float($value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'),
            default => (string) $value,
        };
    }
}
