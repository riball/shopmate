@extends('layouts.app')

@php($facets = app(\App\Services\Catalog\BrowseQuery::class)->facets($category))

@section('content')
<div class="wrap" id="browse-root" data-category="{{ $category->slug }}" data-api="{{ route('api.products.index') }}">
    <header class="browse-head">
        <div>
            <h1>{{ $category->name }}</h1>
            <p class="muted num" style="margin:4px 0 0">{{ $facets['total'] }} products compared across stores{{ $facets['total'] ? ', ₹'.number_format($facets['min_price']).' to ₹'.number_format($facets['max_price']) : '' }}.</p>
        </div>
        <button class="btn btn-secondary btn-small filter-toggle" type="button" aria-expanded="false" aria-controls="filters">Filters</button>
    </header>

    <div class="browse-layout">
        <form class="filters" id="filters" aria-label="Filters">
            <fieldset>
                <legend>Price (₹)</legend>
                <div class="price-inputs">
                    <div><label class="visually-hidden" for="min_price">Minimum price</label><input type="number" id="min_price" name="min_price" min="0" step="100" placeholder="Min" inputmode="numeric"></div>
                    <div><label class="visually-hidden" for="max_price">Maximum price</label><input type="number" id="max_price" name="max_price" min="0" step="100" placeholder="Max" inputmode="numeric"></div>
                </div>
            </fieldset>

            @if ($facets['must_haves'])
                <fieldset>
                    <legend>Must-haves</legend>
                    <p class="hint" style="margin:-4px 0 8px">Only products whose listing confirms it.</p>
                    @foreach ($facets['must_haves'] as $key => $label)
                        <label class="check"><input type="checkbox" name="must[]" value="{{ $key }}"> {{ $label }}</label>
                    @endforeach
                </fieldset>
            @endif

            @if ($facets['brands'])
                <fieldset>
                    <legend>Brand</legend>
                    @foreach ($facets['brands'] as $brand => $count)
                        <label class="check"><input type="checkbox" name="brands[]" value="{{ $brand }}"> {{ $brand }} <small class="num">{{ $count }}</small></label>
                    @endforeach
                </fieldset>
            @endif

            <fieldset>
                <legend>Rating</legend>
                <label class="visually-hidden" for="min_rating">Minimum rating</label>
                <select id="min_rating" name="min_rating">
                    <option value="">Any rating</option>
                    <option value="4.5">4.5 and up</option>
                    <option value="4">4 and up</option>
                    <option value="3.5">3.5 and up</option>
                </select>
            </fieldset>

            <button type="reset" class="btn btn-secondary btn-small">Clear filters</button>
        </form>

        <section aria-labelledby="results-count">
            <div class="toolbar">
                <p id="results-count" class="muted num" style="margin:0" aria-live="polite">Loading…</p>
                <div>
                    <label class="visually-hidden" for="sort">Sort by</label>
                    <select id="sort" name="sort" form="filters">
                        @foreach ($facets['sorts'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="product-grid" id="product-grid"></div>
            <nav class="pager" id="pager" aria-label="Pages"></nav>
        </section>
    </div>
</div>

<template id="tile-icon">@include('partials.category-icon', ['slug' => $category->slug])</template>
@endsection

@push('scripts')
<script src="{{ asset('js/browse.js') }}?v={{ filemtime(public_path('js/browse.js')) }}"></script>
@endpush
