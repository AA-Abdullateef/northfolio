<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');

        // abort(404) — not 401/403 — so the admin panel is invisible to anyone
        // who isn't authenticated as an admin. A redirect would confirm the panel
        // exists; a 404 does not.
        if (! $guard->check() || ! $guard->user()->isAdmin()) {
            abort(404);
        }

        return $next($request);
    }
}