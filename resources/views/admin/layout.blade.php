@extends('layouts.app', ['seo' => ['title' => 'Admin | ShopMate', 'description' => 'ShopMate admin', 'keywords' => null, 'noindex' => true]])

@section('content')
<div class="admin-bar">
    <div class="wrap">
        <strong>Admin</strong>
        <a href="{{ route('admin.seo.index') }}" @class(['active' => request()->routeIs('admin.seo.*')])>SEO</a>
        <a href="{{ route('admin.ops') }}" @class(['active' => request()->routeIs('admin.ops')])>Operations</a>
        <a href="{{ route('admin.partners.index') }}" @class(['active' => request()->routeIs('admin.partners.*')])>Partners</a>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="link-btn" type="submit">Log out</button></form>
    </div>
</div>
<div class="wrap" style="padding-top:24px">
    @if (session('status'))
        <div class="alert alert-ok" role="status" style="margin:0 0 16px">{{ session('status') }}</div>
    @endif
    @yield('admin')
</div>
@endsection
