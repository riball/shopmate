@extends('layouts.app')

@section('content')
<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <h1>Say what you need. Get three picks you can check.</h1>
            <p class="hero-lede">ShopMate compares stores, ranks fairly, and checks every statement against the listing data before you see it. Nothing is bought without your approval.</p>

            <form class="slip" id="search-form" novalidate>
                <label for="query">What are you shopping for?</label>
                <textarea id="query" name="query" maxlength="300" required
                          placeholder="e.g. True wireless earbuds under ₹2,000 with a good mic"></textarea>
                <div class="slip-actions">
                    <button class="btn" type="submit">Find my 3 picks</button>
                    <p class="hint">Mention a budget and anything you can't do without.</p>
                </div>
                <p class="field-error" id="search-error" hidden></p>
            </form>

            <ul class="examples" aria-label="Example requests">
                @foreach ($examples as $example)
                    <li><button type="button" class="example-chip" data-example="{{ $example }}">{{ $example }}</button></li>
                @endforeach
            </ul>
        </div>

        <aside class="receipt-preview" aria-labelledby="what-you-get">
            <h2 id="what-you-get">What comes back</h2>
            <div class="receipt-line"><span class="status status-met">✓</span><span><strong>A work order</strong> showing how your request was understood: product type, budget, must-haves.</span></div>
            <div class="receipt-line"><span class="status status-met">✓</span><span><strong>Three products</strong> ranked by review-weighted rating, with the cheapest store for each.</span></div>
            <div class="receipt-line"><span class="status status-unknown">?</span><span><strong>Honest gaps.</strong> If a listing doesn't say something, it's marked "couldn't confirm", never "yes".</span></div>
            <div class="receipt-line"><span class="cite-chip" tabindex="-1">T4</span><span><strong>Checked statements.</strong> Each sentence names the step its facts came from. Anything that fails the check is removed.</span></div>
        </aside>
    </div>
</section>

<section class="categories" aria-labelledby="browse-heading">
    <div class="wrap">
        <h2 id="browse-heading">Or browse a category</h2>
        @if (empty($categories))
            <div class="panel empty-state">
                <h3>No products loaded yet</h3>
                <p class="muted">Run <code>php artisan migrate --seed</code> to load the demo catalog.</p>
            </div>
        @else
            <div class="category-grid">
                @foreach ($categories as $category)
                    <a class="category-tile" href="{{ route('browse', $category['slug']) }}">
                        @include('partials.category-icon', ['slug' => $category['slug']])
                        <div>
                            <strong>{{ $category['name'] }}</strong>
                            <span class="num">{{ $category['products_count'] }} products{{ $category['from_price'] ? ', from ₹'.number_format($category['from_price']) : '' }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/search.js') }}?v={{ filemtime(public_path('js/search.js')) }}"></script>
@endpush
