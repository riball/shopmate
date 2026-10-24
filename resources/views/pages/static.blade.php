@extends('layouts.app')

@section('content')
<article class="wrap">
    <div class="prose">
        <h1>{{ $page->title }}</h1>
        {{-- Page bodies are written by the site owner in the seeder/admin, so their HTML is trusted. --}}
        {!! $page->body !!}
        <p><a class="btn" href="{{ route('home') }}">Try a search</a></p>
    </div>
</article>
@endsection
