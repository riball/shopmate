@extends('admin.layout')

@section('admin')
<p class="breadcrumb"><a href="{{ route('admin.seo.index', ['type' => $type]) }}">SEO: {{ ucfirst($type) }}s</a></p>
<h1 style="font-size:1.6rem">{{ $model->name ?? $model->title }}</h1>
<p class="muted">Leave a field empty to use the generated text (shown as the placeholder).</p>

<div class="product-grid-2">
    <form method="post" action="{{ route('admin.seo.update', [$type, $model->id]) }}" class="panel" id="seo-form">
        @csrf
        @method('PUT')

        <div class="field">
            <label for="title">Title <span class="counter" data-for="title" data-max="60"></span></label>
            <input type="text" id="title" name="title" maxlength="70" value="{{ old('title', $meta?->title) }}" placeholder="{{ $defaults['title'] }}">
            <p class="hint">Search engines show about 60 characters.</p>
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="description">Description <span class="counter" data-for="description" data-max="155"></span></label>
            <textarea id="description" name="description" maxlength="170" rows="3" placeholder="{{ $defaults['description'] }}">{{ old('description', $meta?->description) }}</textarea>
            <p class="hint">About 155 characters are shown.</p>
            @error('description')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="keywords">Keywords</label>
            <input type="text" id="keywords" name="keywords" maxlength="255" value="{{ old('keywords', $meta?->keywords) }}" placeholder="{{ $defaults['keywords'] ?? '' }}">
        </div>

        <label class="check" style="display:flex;gap:8px;font-weight:400;margin-bottom:16px">
            <input type="hidden" name="noindex" value="0">
            <input type="checkbox" name="noindex" value="1" @checked(old('noindex', $meta?->noindex))> Hide this page from search engines (noindex, and left out of the sitemap)
        </label>

        <div class="card-actions">
            <button class="btn" type="submit" name="action" value="save">Save SEO</button>
            @if ($meta)
                <button class="btn btn-secondary" type="submit" name="action" value="reset">Reset to generated</button>
            @endif
        </div>
    </form>

    <section class="panel" aria-labelledby="preview-heading">
        <h2 id="preview-heading">Search result preview</h2>
        <div class="serp" id="serp"
             data-default-title="{{ $defaults['title'] }}"
             data-default-description="{{ $defaults['description'] }}">
            <div class="serp-url">{{ $url }}</div>
            <div class="serp-title" id="serp-title"></div>
            <p class="serp-desc" id="serp-desc"></p>
        </div>
        <p class="hint" style="margin-top:10px"><a href="{{ $url }}" target="_blank" rel="noopener">Open the page</a></p>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
@endpush
