<?php

namespace App\Models;

use App\Enums\PartnerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * A retailer allowed to push prices for its own store through the partner API.
 *
 * The secret is stored encrypted, not hashed: checking an HMAC signature
 * needs the original secret, so the server must be able to read it back.
 */
class Partner extends Model
{
    protected $fillable = ['name', 'store_id', 'key_id', 'secret', 'allowed_ips', 'status', 'rate_limit_per_minute', 'last_used_at'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'allowed_ips' => 'array',
            'status' => PartnerStatus::class,
            'rate_limit_per_minute' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function newKeyId(): string
    {
        return 'pk_'.Str::lower(Str::random(24));
    }

    public static function newSecret(): string
    {
        return 'sk_'.Str::random(40);
    }

    /** An empty list allows nobody: forgetting to configure it fails closed, not open. */
    public function allowsIp(?string $ip): bool
    {
        $ranges = array_values(array_filter((array) $this->allowed_ips));

        return $ip !== null && $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }
}
