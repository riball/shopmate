@extends('admin.layout')

@section('admin')
<h1 style="font-size:1.8rem">SEO</h1>
<p class="muted">Every page gets a generated title and description. Edit any of them here; your version replaces the generated one.</p>

<nav class="tabs" aria-label="Page types">
    @foreach ($types as $t)
        <a href="{{ route('admin.seo.index', ['type' => $t]) }}" @class(['active' => $t === $type])>
            {{ ucfirst($t) }}s <span class="num">({{ $counts[$t]['custom'] }}/{{ $counts[$t]['total'] }} edited)</span>
        </a>
    @endforeach
</nav>

<form method="get" class="toolbar" style="justify-content:flex-start">
    <input type="hidden" name="type" value="{{ $type }}">
    <label class="visually-hidden" for="q">Search</label>
    <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Search by name" style="max-width:260px">
    <label class="visually-hidden" for="filter">Show</label>
    <select id="filter" name="filter" style="width:auto">
        <option value="">All</option>
        <option value="custom" @selected(request('filter') === 'custom')>Edited</option>
        <option value="default" @selected(request('filter') === 'default')>Using generated SEO</option>
    </select>
    <button class="btn btn-secondary btn-small" type="submit">Apply</button>
</form>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Name</th><th>Title shown in search results</th><th>Status</th><th><span class="visually-hidden">Edit</span></th></tr></thead>
        <tbody>
        @forelse ($items as $item)
            @php($data = $item->seoData())
            <tr>
                <td>{{ $item->name ?? $item->title }}</td>
                <td>{{ $data['title'] }}</td>
                <td>
                    @if ($data['custom'])<span class="pill pill-ok">Edited</span>@else<span class="pill">Generated</span>@endif
                    @if ($data['noindex'])<span class="pill pill-failed">Hidden from search</span>@endif
                </td>
                <td><a href="{{ route('admin.seo.edit', [$type, $item->id]) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="muted">Nothing matches.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pager">{{ $items->links('admin.pagination') }}</div>
@endsection
