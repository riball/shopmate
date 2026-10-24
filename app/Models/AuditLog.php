<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only record of every cart and checkout step. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['event', 'subject_type', 'subject_id', 'data', 'ip'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }
}
