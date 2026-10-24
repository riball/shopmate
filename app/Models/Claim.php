<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Claim extends Model
{
    protected $fillable = ['search_session_id', 'product_id', 'text', 'cites', 'facts', 'status', 'attempt', 'reason'];

    protected function casts(): array
    {
        return ['facts' => 'array'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
