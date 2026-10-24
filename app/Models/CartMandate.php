<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartMandate extends Model
{
    use HasUuids;

    protected $fillable = [
        'search_session_id', 'product_id', 'product_offer_id', 'quantity', 'unit_price', 'total',
        'payload', 'signature', 'status', 'consented_at', 'expires_at', 'used_at', 'order_ref',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'unit_price' => 'float',
            'total' => 'float',
            'consented_at' => 'datetime',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }
}
