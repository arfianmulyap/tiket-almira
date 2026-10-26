<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminPasswordRequest;
use App\Http\Requests\Admin\UpdateAdminProfileRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * The signed-in admin's own profile (Phase 11).
 *
 * Self-service, so it is guarded by authentication only: a role that may not
 * manage other accounts must still be able to change its own name and password.
 * Email is deliberately not editable here — it is the sign-in identifier and
 * would need its own verification flow.
 */
class AdminProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'user' => $request->user()->loadMissing('role'),
        ]);
    }

    public function update(UpdateAdminProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $previousName = $user->name;

        $user->name = (string) $request->validated('name');
        $user->save();

        $this->audit->record($user, AuditAction::AdminProfileUpdated, $user, [
            'previous_name' => $previousName,
            'name' => $user->name,
        ]);

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', 'Nama berhasil diperbarui.');
    }

    public function updatePassword(UpdateAdminPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->password = (string) $request->validated('password');
        $user->save();

        // Sessions elsewhere were opened with the old password. The current one
        // is kept so the admin is not signed out in the middle of the action.
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $this->audit->record($user, AuditAction::AdminPasswordUpdated, $user);

        return redirect()
            ->route('admin.profile.edit')
            ->with('success', 'Password berhasil diperbarui. Sesi di perangkat lain sudah diakhiri.');
    }
}
