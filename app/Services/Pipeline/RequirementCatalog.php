<?php

namespace App\Services\Pipeline;

/**
 * Reads the supported categories and must-haves from config/shopmate_specs.php
 * and builds Requirement objects from what the planner asked for.
 */
class RequirementCatalog
{
    public function categories(): array
    {
        return collect(config('shopmate_specs'))->map(fn ($c) => $c['name'])->all();
    }

    public function exists(?string $category): bool
    {
        return $category !== null && config("shopmate_specs.{$category}") !== null;
    }

    /** @return array<string, array> requirement key => definition */
    public function definitions(string $category): array
    {
        return config("shopmate_specs.{$category}.requirements", []);
    }

    /** A compact description of every supported must-have, for the AI prompt. */
    public function describeForPrompt(): array
    {
        $out = [];
        foreach (config('shopmate_specs') as $slug => $category) {
            foreach ($category['requirements'] as $key => $def) {
                $out[$slug][$key] = str_replace(':value', 'N', $def['label'])
                    .(! empty($def['param']) ? " (value = N, default {$def['value']})" : '');
            }
        }

        return $out;
    }

    /** Build a Requirement, or null if the key isn't supported in this category. */
    public function make(string $category, string $key, mixed $value = null): ?Requirement
    {
        $def = $this->definitions($category)[$key] ?? null;
        if ($def === null) {
            return null;
        }
        $param = ! empty($def['param']);
        $finalValue = $param && is_numeric($value) && $value > 0 ? (float) $value + 0 : ($def['value'] ?? null);

        return new Requirement(
            key: $key,
            label: str_replace(':value', $this->formatNumber($finalValue), $def['label']),
            spec: $def['spec'],
            op: $def['op'],
            value: $finalValue,
        );
    }

    private function formatNumber(mixed $value): string
    {
        if (! is_numeric($value)) {
            return (string) (is_array($value) ? implode('/', $value) : $value);
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.');
    }
}
