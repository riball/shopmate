<?php

namespace App\Enums;

/**
 * The per-partner kill switch. One partner can be cut off, or limited to
 * reading, without stopping the API for everyone else.
 */
enum PartnerStatus: string
{
    case Active = 'active';
    case ReadOnly = 'read_only';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::ReadOnly => 'Read-only',
            self::Suspended => 'Suspended',
        };
    }

    public function allowsReads(): bool
    {
        return $this !== self::Suspended;
    }

    public function allowsWrites(): bool
    {
        return $this === self::Active;
    }
}
