<?php

namespace App\Http\Middleware;

use App\Enums\AdminAccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Ensure the authenticated user is allowed to use the admin panel.
     *
     * Authentication itself is handled by the `auth` middleware, so this
     * middleware verifies the account flag, the account status, and that a role
     * exists.
     *
     * The status is re-checked on every request rather than only at login
     * (Phase 11): deactivating an account must take effect immediately, not on
     * the admin's next sign-in. An invited account is refused with an
     * explanation; a deactivated one is signed out outright.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isAdmin()) {
            abort(403, 'Anda tidak memiliki akses ke panel admin.');
        }

        if ($user->status === AdminAccountStatus::Invited) {
            abort(403, 'Undangan akun ini belum diterima. Buka tautan undangan di email Anda untuk membuat password.');
        }

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('error', 'Akun Anda sudah dinonaktifkan. Hubungi Super Admin jika ini keliru.');
        }

        if ($user->role_id === null) {
            abort(403, 'Akun admin Anda belum memiliki role. Hubungi Super Admin.');
        }

        return $next($request);
    }
}
