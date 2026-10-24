@extends('layouts.app')

@php
    $best = $product->offers->firstWhere('in_stock', true) ?? $product->offers->first();
@endphp

@push('head')
{{-- Structured data so search engines can show price and rating (schema.org Product) --}}
<script type="application/ld+json">{!! json_encode(array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $product->name,
    'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
    'aggregateRating' => $product->rating && $product->review_count ? ['@type' => 'AggregateRating', 'ratingValue' => $product->rating, 'reviewCount' => $product->review_count] : null,
    'offers' => $product->offers->count() ? [
        '@type' => 'AggregateOffer',
        'priceCurrency' => 'INR',
        'lowPrice' => $product->offers->min('price'),
        'highPrice' => $product->offers->max('price'),
        'offerCount' => $product->offers->count(),
    ] : null,
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
<div class="wrap" id="product-root" data-history="{{ route('api.products.history', $product) }}" data-alerts="{{ route('api.alerts.store') }}" data-product="{{ $product->id }}">
    <div class="product-head">
        <div>
            <p class="breadcrumb"><a href="{{ route('home') }}">Home</a> / <a href="{{ route('browse', $product->category) }}">{{ $product->category->name }}</a></p>
            <h1>{{ $product->name }}</h1>
            <p class="muted num">
                @if ($product->rating !== null)
                    Rated {{ $product->rating }} from {{ number_format($product->review_count) }} reviews.
                    Review-weighted rating: {{ number_format($product->weighted_rating, 2) }}.
                @else
                    No rating yet.
                @endif
            </p>
            @if ($product->isDemo())
                <p><span class="tag tag-demo">Demo</span> <span class="hint">Fictional product from the demo catalog. Prices and stores are made up.</span></p>
            @endif
        </div>

        @if ($best)
            <aside class="best-box" aria-label="Best price">
                <p class="muted" style="margin:0">Lowest price</p>
                <div class="price num">₹{{ number_format($best->price) }}</div>
                <p style="margin:0 0 12px">at <strong>{{ $best->store->name }}</strong>
                    @if ($best->isConverted())<span class="tag tag-approx" title="{{ $best->original_currency }} {{ $best->original_price }} × {{ $best->fx_rate }}">approx.</span>@endif
                </p>
                @if ($best->url)
                    <a class="btn btn-price" href="{{ $best->url }}" rel="nofollow noopener" target="_blank">Go to {{ $best->store->name }}</a>
                @endif
            </aside>
        @endif
    </div>

    <section class="panel" aria-labelledby="stores-heading">
        <h2 id="stores-heading">Prices by store</h2>
        <div class="table-wrap" style="border:0">
            <table class="offers">
                <thead><tr><th>Store</th><th class="num">Price</th><th class="num">MRP</th><th>Checked</th><th><span class="visually-hidden">Link</span></th></tr></thead>
                <tbody>
                @foreach ($product->offers as $offer)
                    <tr @class(['cheapest' => $best && $offer->id === $best->id])>
                        <td>{{ $offer->store->name }} @if ($best && $offer->id === $best->id)<span class="tag tag-approx">Cheapest</span>@endif @if (! $offer->in_stock)<span class="tag tag-stale">Out of stock</span>@endif</td>
                        <td class="num"><strong>₹{{ number_format($offer->price) }}</strong></td>
                        <td class="num muted">{{ $offer->mrp ? '₹'.number_format($offer->mrp) : '—' }}</td>
                        <td class="muted">{{ $offer->last_checked_at?->diffForHumans() ?? 'never' }} @if ($offer->isStale())<span class="tag tag-stale">may be out of date</span>@endif</td>
                        <td>@if ($offer->url)<a href="{{ $offer->url }}" rel="nofollow noopener" target="_blank">Visit</a>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div class="product-grid-2">
        <section class="panel" aria-labelledby="history-heading">
            <h2 id="history-heading">Price history</h2>
            <div class="chart-box"><canvas id="history-chart" aria-label="Price history by store" role="img"></canvas></div>
            <p class="hint" id="history-note" style="margin:8px 0 0"></p>
        </section>

        <section class="panel" aria-labelledby="specs-heading">
            <h2 id="specs-heading">Specs from the listing</h2>
            @if ($product->specFacts->isEmpty())
                <p class="muted">This listing doesn't state any specs we can check.</p>
            @else
                <table class="spec-table">
                    <tbody>
                    @foreach ($product->specFacts->sortBy('key') as $fact)
                        <tr>
                            <th scope="row">{{ \App\Support\SpecLabel::for($product->category->slug, $fact->key) }}</th>
                            <td>
                                @if ($fact->is_conflict)
                                    <span class="status status-conflict" aria-hidden="true">?</span> The listing contradicts itself ({{ $fact->raw_value }}), so no value is used.
                                @else
                                    {{ $fact->display() }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <section class="panel" aria-labelledby="alert-heading">
        <h2 id="alert-heading">Tell me when it gets cheaper</h2>
        <p class="muted">We check prices every few hours and email you once when any store reaches your price.</p>
        <form class="alert-form" id="alert-form" novalidate>
            <div><label for="alert-email">Email</label><input type="email" id="alert-email" required autocomplete="email" placeholder="you@example.com"></div>
            <div><label for="alert-price">Target price (₹)</label><input type="number" id="alert-price" required min="1" step="1" inputmode="numeric" value="{{ $best ? (int) floor($best->price * 0.9 / 10) * 10 : '' }}"></div>
            <button class="btn" type="submit">Set alert</button>
        </form>
        <div class="alert" id="alert-message" hidden></div>
    </section>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chart-4.6.1.umd.min.js') }}" defer></script>
<script src="{{ asset('js/product.js') }}?v={{ filemtime(public_path('js/product.js')) }}" defer></script>
@endpush
