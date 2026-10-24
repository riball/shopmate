<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function show(): View
    {
        return view('admin.login', ['enabled' => (bool) config('shopmate.admin_password')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $key = 'admin-login:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['password' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }

        $expected = (string) config('shopmate.admin_password');
        if ($expected === '' || ! hash_equals($expected, (string) $request->input('password'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['password' => 'That password is not correct.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put('is_admin', true);

        return redirect()->intended(route('admin.seo.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('is_admin');
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}
