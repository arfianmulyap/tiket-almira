<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login form.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended($this->landingUrl($request->user()));
    }

    /**
     * The first admin screen the signed-in admin may actually open.
     *
     * The dashboard needs `dashboard.view`, which a role does not necessarily
     * hold (Phase 10). Sending such an admin straight to the dashboard would
     * greet them with a 403 on every login, so the first permitted screen is
     * used instead. Falling back to the dashboard is deliberate: the admin
     * middleware explains that the account has no role, which is clearer than
     * a redirect that goes nowhere.
     */
    private function landingUrl(?User $user): string
    {
        if ($user?->hasPermission(AdminPermission::DashboardView)) {
            return route('admin.dashboard');
        }

        $screens = [
            [AdminPermission::EventsView, 'admin.events.index'],
            [AdminPermission::OrdersView, 'admin.orders.index'],
            [AdminPermission::CheckInPerform, 'admin.check-in.show'],
            [AdminPermission::RolesView, 'admin.roles.index'],
            [AdminPermission::PermissionsManage, 'admin.permissions.index'],
        ];

        foreach ($screens as [$permission, $routeName]) {
            if ($user?->hasPermission($permission)) {
                return route($routeName);
            }
        }

        return route('admin.dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
