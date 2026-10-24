<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasSeo;

    protected $fillable = [
        'category_id', 'source', 'external_id', 'slug', 'name', 'brand', 'model_group',
        'image_url', 'rating', 'review_count', 'best_price', 'best_offer_id',
        'offers_count', 'weighted_rating', 'captured_on',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'review_count' => 'integer',
            'best_price' => 'float',
            'weighted_rating' => 'float',
            'captured_on' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(ProductOffer::class);
    }

    public function bestOffer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'best_offer_id');
    }

    public function specFacts(): HasMany
    {
        return $this->hasMany(SpecFact::class);
    }

    public function scopeInCategory(Builder $query, Category $category): Builder
    {
        return $query->where('category_id', $category->id);
    }

    /** Typed facts as key => SpecFact, for the pipeline and the spec table. */
    public function factMap(): array
    {
        return $this->specFacts->keyBy('key')->all();
    }

    /**
     * Re-compute the copied "best price" columns from the offers.
     * Called after imports and price refreshes, so browse queries stay simple and fast.
     */
    public function refreshBestOffer(): void
    {
        $best = $this->offers()->where('in_stock', true)->orderBy('price')->first();

        $this->forceFill([
            'best_price' => $best?->price,
            'best_offer_id' => $best?->id,
            'offers_count' => $this->offers()->count(),
        ])->save();
    }

    public function isDemo(): bool
    {
        return $this->source === 'demo';
    }

    public function defaultSeo(): array
    {
        $price = $this->best_price ? ' from ₹'.number_format($this->best_price) : '';

        return [
            'title' => str($this->name)->limit(45, '')." price{$price} | ShopMate",
            'description' => "Compare {$this->name} across {$this->offers_count} store(s){$price}. Verified specs, ratings and price history.",
            'keywords' => strtolower(trim(($this->brand ?? '').' '.$this->model_group)).', price, compare',
        ];
    }

    public function publicUrl(): string
    {
        return route('products.show', $this);
    }
}
