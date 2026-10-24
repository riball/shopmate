<?php

namespace App\Services\Pipeline;

use App\Models\SpecFact;

/**
 * One must-have the shopper asked for, e.g. "at least 8 GB RAM", plus the
 * rule that checks it against a product's typed spec facts.
 */
final class Requirement
{
    public const MET = 'met';

    public const NOT_MET = 'not_met';

    public const UNKNOWN = 'unknown';         // listing is silent

    public const CONFLICT = 'conflict';       // listing contradicts itself

    public const UNSUPPORTED = 'unsupported'; // we have no rule for this requirement

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $spec,
        public readonly ?string $op,
        public readonly mixed $value = null,
    ) {}

    public static function unsupported(string $text): self
    {
        return new self('unsupported:'.md5($text), ucfirst(trim($text)), null, null);
    }

    public function isSupported(): bool
    {
        return $this->spec !== null;
    }

    /** "Not stated in the listing" is never treated as a yes. */
    public function evaluate(?SpecFact $fact): string
    {
        if (! $this->isSupported()) {
            return self::UNSUPPORTED;
        }
        if ($fact === null) {
            return self::UNKNOWN;
        }
        if ($fact->is_conflict) {
            return self::CONFLICT;
        }
        $actual = $fact->value();
        if ($actual === null) {
            return self::UNKNOWN;
        }

        $passes = match ($this->op) {
            '>=' => is_numeric($actual) && $actual >= (float) $this->value,
            '<=' => is_numeric($actual) && $actual <= (float) $this->value,
            '==' => mb_strtolower((string) $actual) === mb_strtolower((string) $this->value),
            'contains' => str_contains(mb_strtolower((string) $actual), mb_strtolower((string) $this->value)),
            'one_of' => in_array(mb_strtolower((string) $actual), array_map('mb_strtolower', (array) $this->value), true),
            'true' => $actual === true,
            default => false,
        };

        return $passes ? self::MET : self::NOT_MET;
    }

    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'spec' => $this->spec, 'op' => $this->op, 'value' => $this->value];
    }
}
