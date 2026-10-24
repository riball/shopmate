<?php

namespace App\Models\Concerns;

use App\Models\SeoMeta;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives any model an editable SEO record through a polymorphic relationship
 * (one seo_meta table serves categories, products and pages alike).
 * Models using this trait must implement defaultSeo(): array.
 */
trait HasSeo
{
    public function seo(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /** Admin-edited values win; generated defaults fill any gaps. */
    public function seoData(): array
    {
        $defaults = $this->defaultSeo();
        $meta = $this->seo;

        return [
            'title' => $meta?->title ?: $defaults['title'],
            'description' => $meta?->description ?: $defaults['description'],
            'keywords' => $meta?->keywords ?: ($defaults['keywords'] ?? null),
            'noindex' => (bool) ($meta?->noindex ?? false),
            'custom' => $meta !== null,
        ];
    }

    abstract public function defaultSeo(): array;

    abstract public function publicUrl(): string;
}
