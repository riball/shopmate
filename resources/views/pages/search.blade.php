@extends('layouts.app')

@section('content')
<div class="wrap" id="search-root"
     data-id="{{ $session->id }}"
     data-api="{{ route('api.searches.show', $session) }}"
     data-clarify="{{ route('api.searches.clarify', $session) }}"
     data-mandates="{{ route('api.mandates.store') }}">

    <header class="search-head">
        <p class="muted" style="margin:0 0 4px">Your request</p>
        <h1><span class="query-quote">{{ $session->query }}</span></h1>
        <p class="muted" id="clarified-line" @if (! $session->clarification_answer) hidden @endif>
            Your follow-up: <strong id="clarified-answer">{{ $session->clarification_answer }}</strong>
        </p>
        <p><a href="{{ route('home') }}">Start a new search</a></p>
    </header>

    <div class="search-layout">
        <div>
            <div id="status-area">
                <div class="panel progress" id="progress">
                    <span class="spinner" aria-hidden="true"></span>
                    <span id="progress-text">Reading your request…</span>
                </div>
            </div>

            <section class="panel work-order" id="work-order" hidden aria-labelledby="wo-heading">
                <h2 id="wo-heading">Work order</h2>
                <dl>
                    <dt>Product</dt><dd id="wo-category">—</dd>
                    <dt>Budget</dt><dd id="wo-budget" class="num">—</dd>
                    <dt>Must-haves</dt><dd><ul class="must-list" id="wo-musts"></ul></dd>
                </dl>
            </section>

            <div class="replan-note" id="replan-note" role="note" hidden>
                <strong>Nothing matched every requirement, so the search was relaxed:</strong>
                <ul id="replan-list"></ul>
            </div>

            <div class="verified-banner" id="banner" hidden>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l7 3v6c0 5-3 8.5-7 11-4-2.5-7-6-7-11V5z"/><path d="m8.5 12 2.5 2.5 4.5-5"/></svg>
                <span id="banner-text"></span>
            </div>

            <section id="results" hidden aria-labelledby="picks-heading">
                <h2 id="picks-heading" class="visually-hidden">Top picks</h2>
                <div class="cards" id="cards"></div>

                <h2>Side by side</h2>
                <div class="compare-wrap">
                    <table class="compare" id="compare"></table>
                </div>
            </section>
        </div>

        <aside>
            <details class="panel trace-panel" id="trace-panel" open>
                <summary>How it got here</summary>
                <p class="trace-meta" id="trace-meta">Each step is recorded as it happens. Steps with an ID are tool steps that statements can cite.</p>
                <ol class="trace" id="trace"></ol>
            </details>
        </aside>
    </div>
</div>

{{-- Cart approval (mock checkout) --}}
<div class="modal-backdrop" id="mandate-modal" hidden>
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="mandate-title">
        <h2 id="mandate-title">Approve this cart</h2>
        <p class="muted">The server looks up the price itself and signs exactly this cart. Checkout is refused if anything changes, the price moves, or 15 minutes pass. This is a mock: no money is charged.</p>

        <div class="cart-summary" id="cart-summary"></div>

        <div id="mandate-step-consent">
            <div class="field">
                <label for="mandate-qty">Quantity</label>
                <select id="mandate-qty" style="width:auto">
                    @for ($i = 1; $i <= 5; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                </select>
            </div>
            <label class="consent">
                <input type="checkbox" id="mandate-consent">
                <span>I approve buying exactly this item at this price from this store.</span>
            </label>
        </div>

        <div id="mandate-step-signed" hidden>
            <p><strong>Cart signed.</strong> It's valid until <span id="mandate-expiry"></span>.</p>
            <p class="signature">Signature (HMAC-SHA256): <span id="mandate-signature"></span></p>
        </div>

        <div class="alert" id="mandate-message" hidden></div>

        <div class="modal-actions">
            <button class="btn btn-secondary" type="button" data-close-modal>Close</button>
            <button class="btn" type="button" id="mandate-approve" disabled>Approve cart</button>
            <button class="btn btn-price" type="button" id="mandate-checkout" hidden>Place mock order</button>
        </div>
    </div>
</div>
@endsection

@section('body-class', 'page-search')

@push('scripts')
<script src="{{ asset('js/search.js') }}?v={{ filemtime(public_path('js/search.js')) }}"></script>
@endpush
