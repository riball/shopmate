@extends('admin.layout')

@section('admin')
<h1 style="font-size:1.8rem">Operations</h1>
<p class="muted">The last 7 days of searches, the checkout audit log and failed background jobs. Use it to spot problems before shoppers report them.</p>

<div class="stat-row">
    <div class="stat"><b class="num">{{ (int) $summary->searches }}</b>Searches</div>
    <div class="stat"><b class="num">{{ (int) $summary->done }}</b>With results</div>
    <div class="stat"><b class="num">{{ (int) $summary->clarifying }}</b>Asked a question</div>
    <div class="stat"><b class="num">{{ (int) $summary->no_results }}</b>No results</div>
    <div class="stat"><b class="num">{{ (int) $summary->failed }}</b>Failed</div>
    <div class="stat"><b class="num">{{ $summary->avg_ms ? number_format($summary->avg_ms / 1000, 1).' s' : '—' }}</b>Average time</div>
    <div class="stat"><b class="num">{{ (int) $summary->claims_removed }}/{{ (int) $summary->claims_checked }}</b>Claims removed / checked</div>
</div>

<h2>Recent searches</h2>
<div class="table-wrap" style="margin-bottom:24px">
    <table class="data-table">
        <thead><tr><th>When</th><th>Request</th><th>Status</th><th>AI</th><th class="num">Time</th><th class="num">Claims</th></tr></thead>
        <tbody>
        @forelse ($searches as $s)
            <tr>
                <td class="muted">{{ $s->created_at->diffForHumans() }}</td>
                <td><a href="{{ route('search.show', $s) }}">{{ str($s->query)->limit(70) }}</a></td>
                <td><span class="pill pill-{{ $s->status }}">{{ str_replace('_', ' ', $s->status) }}</span></td>
                <td class="muted">{{ $s->llm_driver ?? '—' }}</td>
                <td class="num">{{ $s->duration_ms ? number_format($s->duration_ms / 1000, 1).' s' : '—' }}</td>
                <td class="num">{{ $s->claims_checked - $s->claims_removed }}/{{ $s->claims_checked }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No searches yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2>Checkout audit log</h2>
<div class="table-wrap" style="margin-bottom:24px">
    <table class="data-table">
        <thead><tr><th>When</th><th>Event</th><th>Subject</th><th>Details</th></tr></thead>
        <tbody>
        @forelse ($audit as $entry)
            <tr>
                <td class="muted">{{ $entry->created_at?->diffForHumans() }}</td>
                <td><code>{{ $entry->event }}</code></td>
                <td class="muted">{{ $entry->subject_type }} {{ str($entry->subject_id)->limit(13) }}</td>
                <td class="muted"><code>{{ $entry->data ? json_encode($entry->data) : '' }}</code></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Nothing logged yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2>Failed background jobs</h2>
<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>When</th><th>Error</th></tr></thead>
        <tbody>
        @forelse ($failedJobs as $job)
            <tr><td class="muted">{{ $job->failed_at }}</td><td><code>{{ str(strtok($job->exception, "\n"))->limit(160) }}</code></td></tr>
        @empty
            <tr><td colspan="2" class="muted">None. The queue is healthy.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
