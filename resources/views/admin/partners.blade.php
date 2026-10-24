@extends('admin.layout')

@section('admin')
<h1 style="font-size:1.8rem">Partners</h1>
<p class="muted">Retailers that push their own prices through the partner API. Every request must come from an allowed IP, carry a valid HMAC signature and a fresh one-time nonce. Use the buttons to pause one partner without stopping the others.</p>

<div class="table-wrap" style="margin-bottom:24px">
    <table class="data-table">
        <thead>
            <tr>
                <th>Partner</th>
                <th>Store</th>
                <th>Allowed IPs</th>
                <th>Status</th>
                <th class="num">Updates (24h)</th>
                <th class="num">Rejected (24h)</th>
                <th>Last used</th>
                <th>Kill switch</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($partners as $partner)
            @php($c = $counts->get((string) $partner->id))
            <tr>
                <td><strong>{{ $partner->name }}</strong><br><code class="muted">{{ $partner->key_id }}</code></td>
                <td>{{ $partner->store->name }}</td>
                <td><code>{{ implode(', ', (array) $partner->allowed_ips) ?: '(none: blocked)' }}</code></td>
                <td><span class="pill pill-{{ $partner->status->value }}">{{ $partner->status->label() }}</span></td>
                <td class="num">{{ (int) ($c->updates ?? 0) }}</td>
                <td class="num">{{ (int) ($c->rejected ?? 0) }}</td>
                <td class="muted">{{ $partner->last_used_at?->diffForHumans() ?? 'never' }}</td>
                <td>
                    <div class="status-form">
                        @foreach ($statuses as $status)
                            <form method="post" action="{{ route('admin.partners.status', $partner) }}">
                                @csrf
                                <input type="hidden" name="status" value="{{ $status->value }}">
                                <button type="submit" class="btn btn-small btn-secondary" aria-pressed="{{ $partner->status === $status ? 'true' : 'false' }}" {{ $partner->status === $status ? 'disabled' : '' }}>{{ $status->label() }}</button>
                            </form>
                        @endforeach
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">No partners yet. Create one on the server with <code>php artisan partner:create "Name" store-slug --ip=203.0.113.10</code>.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2>Partner API activity</h2>
<p class="muted">Rejections show the exact reason here, while the caller only gets a generic "unauthorized" and a request ID to quote.</p>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>When</th><th>Event</th><th>IP</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($events as $entry)
            <tr>
                <td class="muted" style="white-space:nowrap">{{ $entry->created_at?->diffForHumans() }}</td>
                <td><span @class(['pill', 'pill-failed' => str_starts_with($entry->event, 'partner.rejected.'), 'pill-ok' => ! str_starts_with($entry->event, 'partner.rejected.')])>{{ $entry->event }}</span></td>
                <td class="muted"><code>{{ $entry->ip ?? '—' }}</code></td>
                <td class="muted" style="word-break:break-all"><code>{{ $entry->data ? json_encode($entry->data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '' }}</code></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">No partner activity yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
