<?php

namespace App\Models;

use App\Models\Concerns\HasSeo;
use Illuminate\Database\Eloquent\Model;

/** Static pages (home, how it works, about) whose SEO is editable in the admin panel. */
class Page extends Model
{
    use HasSeo;

    protected $fillable = ['slug', 'title', 'body'];

    public function defaultSeo(): array
    {
        return [
            'title' => $this->title.' | ShopMate',
            'description' => str(strip_tags((string) $this->body))->squish()->limit(155)->toString()
                ?: 'Compare products across Indian stores with verified facts.',
            'keywords' => 'shopmate, price comparison, india',
        ];
    }

    public function publicUrl(): string
    {
        return $this->slug === 'home' ? route('home') : route('pages.show', $this->slug);
    }
}
