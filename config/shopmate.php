<?php

use App\Services\Partners\Checks\ApiKeyCheck;
use App\Services\Partners\Checks\IpAllowlistCheck;
use App\Services\Partners\Checks\PartnerStatusCheck;
use App\Services\Partners\Checks\RateLimitCheck;
use App\Services\Partners\Checks\SignatureCheck;

return [

    /*
    |--------------------------------------------------------------------------
    | AI model (Groq)
    |--------------------------------------------------------------------------
    | When GROQ_API_KEY is empty, ShopMate uses the built-in rule-based
    | "offline AI" so the whole app still works (and tests need no key).
    */
    'llm' => [
        'driver' => env('LLM_DRIVER', env('GROQ_API_KEY') ? 'groq' : 'offline'),
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'timeout' => (int) env('GROQ_TIMEOUT', 60),
        ],
        // If Groq fails (rate limit, network), fall back to the offline AI
        // and say so in the trace, instead of failing the whole search.
        'fallback_to_offline' => (bool) env('LLM_FALLBACK_TO_OFFLINE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search pipeline
    |--------------------------------------------------------------------------
    */
    'pipeline' => [
        'max_replans' => 2,
        'max_budget_raise' => 0.5,     // replan may raise the budget by up to 50%
        'results' => 3,
        'rating_prior_reviews' => 50,  // "m" in the review-weighted (Bayesian) rating
        'rating_prior_penalty' => 0.5, // unproven products start this far below the category average
        'score_weights' => ['rating' => 0.7, 'requirements' => 0.3],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cart mandate (mock checkout)
    |--------------------------------------------------------------------------
    */
    'mandate' => [
        'secret' => env('MANDATE_SECRET', env('APP_KEY')),
        'ttl_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data sources
    |--------------------------------------------------------------------------
    | "demo"      — fictional catalog in database/data/demo_catalog.json (safe to publish)
    | "local"     — your private Flipkart JSON in storage/app/private/flipkart (never committed)
    | "dummyjson" — public demo API, prices converted from USD with Frankfurter
    */
    'sources' => [
        'local_path' => storage_path('app/private/flipkart'),
        'dummyjson_url' => env('DUMMYJSON_URL', 'https://dummyjson.com'),
        'frankfurter_url' => env('FRANKFURTER_URL', 'https://api.frankfurter.dev/v1'),
    ],

    'stale_after_days' => 7,

    // Live demo only: let the scheduler nudge fictional demo prices so charts show history.
    'demo_simulate_prices' => (bool) env('DEMO_SIMULATE_PRICES', false),

    'admin_password' => env('ADMIN_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Partner API
    |--------------------------------------------------------------------------
    | Retailers push their own prices. Every request passes these checks in
    | order; the first failure stops it and is written to the audit log.
    | Cheap checks run first, and the IP check runs before the signature,
    | so a leaked key and secret are useless from an unknown address.
    */
    'partners' => [
        'checks' => [
            ApiKeyCheck::class,        // who is calling?
            IpAllowlistCheck::class,   // from an allowed address?
            SignatureCheck::class,     // signed, recent, and not replayed?
            PartnerStatusCheck::class, // not suspended / read-only?
            RateLimitCheck::class,     // within its own requests-per-minute?
        ],
        'max_clock_skew_seconds' => 300,
        'max_items_per_request' => 100,
        // Brute-force lockout: after this many failed authentications in a minute, an IP is refused.
        'max_failed_attempts_per_minute' => 10,
    ],
];
