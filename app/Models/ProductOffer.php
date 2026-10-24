<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductOffer extends Model
{
    protected $fillable = [
        'product_id', 'store_id', 'price', 'mrp', 'original_currency', 'original_price',
        'fx_rate', 'url', 'in_stock', 'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'float',
            'mrp' => 'float',
            'original_price' => 'float',
            'fx_rate' => 'float',
            'in_stock' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function isConverted(): bool
    {
        return $this->original_currency !== 'INR';
    }

    /** Not refreshed for a while, so the card says the price may be out of date. */
    public function isStale(): bool
    {
        return $this->last_checked_at === null
            || $this->last_checked_at->lt(now()->subDays(config('shopmate.stale_after_days')));
    }
}
