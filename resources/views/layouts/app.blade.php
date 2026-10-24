@php
    $seo = $seo ?? ['title' => 'ShopMate — compare products with checked facts', 'description' => 'Describe what you want; ShopMate compares stores and checks every statement against the listing data.', 'keywords' => null, 'noindex' => false];
@endphp
<!DOCTYPE html>
<html lang="en-IN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    @if (! empty($seo['keywords']))<meta name="keywords" content="{{ $seo['keywords'] }}">@endif
    @if (! empty($seo['noindex']))<meta name="robots" content="noindex">@endif
    @isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="website">
    @isset($canonical)<meta property="og:url" content="{{ $canonical }}">@endisset
    <meta name="theme-color" content="#1B2840">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400&family=Familjen+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    {{-- ?v= changes when the file changes, so browsers can cache it for a long time safely --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body class="@yield('body-class')">
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="wrap header-row">
        <a class="brand" href="{{ route('home') }}" aria-label="ShopMate home">
            <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect x="3" y="7" width="26" height="20" rx="5" fill="currentColor"/><path d="M11 7V6a5 5 0 0 1 10 0v1" fill="none" stroke="currentColor" stroke-width="2.4"/><path d="m10.5 17.5 3.8 3.8 7.4-7.6" fill="none" stroke="#fff" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span>ShopMate</span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">Menu</button>
        <nav id="site-nav" class="site-nav" aria-label="Main">
            {{-- Cached as a plain array: the cache only stores simple values, never PHP objects --}}
            @foreach (\Illuminate\Support\Facades\Cache::remember('nav:categories', 3600, fn () => \App\Models\Category::orderBy('name')->pluck('name', 'slug')->all()) as $navSlug => $navName)
                <a href="{{ route('browse', $navSlug) }}" @class(['active' => request()->is('c/'.$navSlug)])>{{ $navName }}</a>
            @endforeach
            <a href="{{ route('pages.show', 'how-it-works') }}" @class(['active' => request()->is('pages/how-it-works')])>How it works</a>
        </nav>
    </div>
</header>

<main id="main">
    @yield('content')
</main>

<footer class="site-footer">
    <div class="wrap footer-row">
        <p>ShopMate is a portfolio project. The public demo uses a fictional catalog, and checkout is a mock: no money is ever charged.</p>
        <p class="footer-links">
            <a href="{{ route('pages.show', 'how-it-works') }}">How it works</a>
            <a href="{{ route('pages.show', 'about') }}">About</a>
            <a href="{{ route('sitemap') }}">Sitemap</a>
        </p>
    </div>
</footer>

<div class="toast" role="status" aria-live="polite" hidden></div>

{{-- jQuery is served from this site (public/vendor), so there is no third-party dependency at page load --}}
<script src="{{ asset('vendor/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
@stack('scripts')
</body>
</html>
