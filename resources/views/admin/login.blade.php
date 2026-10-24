@extends('layouts.app', ['seo' => ['title' => 'Admin login | ShopMate', 'description' => 'Admin login', 'keywords' => null, 'noindex' => true]])

@section('content')
<div class="wrap" style="max-width:420px;padding-top:40px">
    <div class="panel">
        <h1 style="font-size:1.6rem">Admin login</h1>
        @if (! $enabled)
            <p class="muted">The admin area is switched off. Set <code>ADMIN_PASSWORD</code> in your <code>.env</code> file to turn it on.</p>
        @else
            <form method="post" action="{{ route('admin.login.attempt') }}">
                @csrf
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autofocus autocomplete="current-password">
                    @error('password')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <button class="btn" type="submit">Log in</button>
            </form>
        @endif
    </div>
</div>
@endsection
