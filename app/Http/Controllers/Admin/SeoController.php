<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * SEO admin: edit the title, description and keywords of any page type.
 * One seo_meta table serves products, categories and pages through a
 * polymorphic relationship, so adding a new page type needs no new table.
 */
class SeoController extends Controller
{
    private const TYPES = [
        'product' => Product::class,
        'category' => Category::class,
        'page' => Page::class,
    ];

    public function index(Request $request): View
    {
        $type = $request->query('type', 'product');
        abort_unless(isset(self::TYPES[$type]), 404);

        $query = self::TYPES[$type]::query()->with('seo');
        if ($q = $request->query('q')) {
            $query->where($type === 'page' ? 'title' : 'name', 'like', '%'.$q.'%');
        }
        if ($request->query('filter') === 'custom') {
            $query->has('seo');
        } elseif ($request->query('filter') === 'default') {
            $query->doesntHave('seo');
        }

        return view('admin.seo.index', [
            'type' => $type,
            'types' => array_keys(self::TYPES),
            'items' => $query->orderBy('id')->paginate(20)->withQueryString(),
            'counts' => collect(self::TYPES)->map(fn ($class) => [
                'total' => $class::count(),
                'custom' => $class::has('seo')->count(),
            ]),
        ]);
    }

    public function edit(string $type, int $id): View
    {
        $model = $this->find($type, $id);

        return view('admin.seo.edit', [
            'type' => $type,
            'model' => $model,
            'defaults' => $model->defaultSeo(),
            'meta' => $model->seo,
            'url' => $model->publicUrl(),
        ]);
    }

    public function update(Request $request, string $type, int $id): RedirectResponse
    {
        $model = $this->find($type, $id);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:70'],
            'description' => ['nullable', 'string', 'max:170'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'noindex' => ['nullable', 'boolean'],
            'action' => ['nullable', Rule::in(['save', 'reset'])],
        ]);

        if (($data['action'] ?? 'save') === 'reset') {
            $model->seo()->delete();
            $message = 'SEO reset to the generated defaults.';
        } else {
            $model->seo()->updateOrCreate([], [
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'keywords' => $data['keywords'] ?? null,
                'noindex' => (bool) ($data['noindex'] ?? false),
            ]);
            $message = 'SEO saved.';
        }

        Cache::forget('sitemap.xml');

        return redirect()->route('admin.seo.edit', [$type, $id])->with('status', $message);
    }

    private function find(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type]::with('seo')->findOrFail($id);
    }
}
