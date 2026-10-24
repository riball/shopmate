<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets only a logged-in admin through (single admin password from ADMIN_PASSWORD in .env). */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('is_admin')) {
            return redirect()->guest(route('admin.login'));
        }

        return $next($request);
    }
}
