<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the authenticated user is allowed to use the admin panel.
     *
     * Authentication itself is handled by the `auth` middleware, so this
     * middleware only verifies the admin authorisation flag.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Anda tidak memiliki akses ke panel admin.');
        }

        return $next($request);
    }
}
