<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TraceStep extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'search_session_id', 'seq', 'step_id', 'node', 'level',
        'thought', 'action', 'observation', 'product_ids', 'data',
    ];

    protected function casts(): array
    {
        return ['product_ids' => 'array', 'data' => 'array'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SearchSession::class, 'search_session_id');
    }
}
