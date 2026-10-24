{{-- Simple line icons per category. Usage: @include('partials.category-icon', ['slug' => 'earphones']) --}}
@switch($slug)
    @case('earphones')
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M14 30v-6a10 10 0 0 1 20 0v6"/><rect x="9" y="28" width="8" height="12" rx="3"/><rect x="31" y="28" width="8" height="12" rx="3"/></svg>
        @break
    @case('smartphones')
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><rect x="14" y="5" width="20" height="38" rx="4"/><path d="M21 37h6"/></svg>
        @break
    @case('laptops')
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="10" width="30" height="20" rx="2"/><path d="M4 36h40l-3 4H7z"/></svg>
        @break
    @case('smartwatches')
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><rect x="13" y="13" width="22" height="22" rx="6"/><path d="M18 13l2-8h8l2 8M18 35l2 8h8l2-8M24 19v5l3 3"/></svg>
        @break
    @case('running-shoes')
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 32c0-4 1-9 3-13l8 5c3 2 7 2 9 0l3-3 11 7c2 1 3 3 3 5v2H6z"/><path d="M6 36h38M20 24l2-3M24 25l2-3"/></svg>
        @break
    @default
        <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><rect x="9" y="14" width="30" height="26" rx="5"/><path d="M17 14v-2a7 7 0 0 1 14 0v2"/></svg>
@endswitch
