<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasSeo;

    protected $fillable = ['slug', 'name'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Spec and requirement rules for this category from config/shopmate_specs.php */
    public function rules(): array
    {
        return config('shopmate_specs.'.$this->slug, []);
    }

    public function defaultSeo(): array
    {
        return [
            'title' => "Compare {$this->name} prices in India | ShopMate",
            'description' => "Compare {$this->name} across stores with verified specs, review-weighted ratings and price history.",
            'keywords' => strtolower($this->name).', price comparison, best price india',
        ];
    }

    public function publicUrl(): string
    {
        return route('browse', $this);
    }
}
