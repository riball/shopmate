<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $table = 'price_history';

    public $timestamps = false;

    protected $fillable = ['product_offer_id', 'price', 'recorded_at'];

    protected function casts(): array
    {
        return ['price' => 'float', 'recorded_at' => 'datetime'];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }
}
