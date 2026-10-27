<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminAccountStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminAccountRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AdminInvitationService;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin account management (Phase 11).
 *
 * Accounts are never created with a password: an admin invites a person, the
 * person chooses their own password through a one-time 24-hour link, and only
 * then does the account become usable. Deactivating an account ends its
 * sessions immediately and can be undone because the password is still on file.
 */
class AdminAccountController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AdminInvitationService $invitations,
    ) {}

    public function index(): View
    {
        $this->authorize('admin-accounts.view');

        return view('admin.accounts.index', [
            'accounts' => User::query()
                ->where('is_admin', true)
                ->with([
                    'role',
                    // Only the still-open invitation matters on this screen;
                    // older rows are kept for the trail.
                    'invitations' => fn ($query) => $query
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->latest('id'),
                    // Phase 13: whether the invitation email actually went out,
                    // so a failed send is visible without opening the log.
                    'invitations.emailDeliveries',
                ])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('admin-accounts.manage');

        return view('admin.accounts.create', [
            'roles' => $this->assignableRoles($request->user()),
            'lifetimeHours' => AdminInvitationService::LIFETIME_HOURS,
        ]);
    }

    /**
     * Create the account in `invited` state and email the invitation link.
     */
    public function store(StoreAdminAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $role = Role::query()->findOrFail($data['role_id']);

        // Handing out the protected role is a Super Admin decision: otherwise
        // anyone allowed to manage accounts could promote themselves.
        if ($role->isProtected() && ! $actor->isSuperAdmin()) {
            return back()
                ->withInput()
                ->with('error', 'Hanya Super Admin yang dapat menugaskan role Super Admin.');
        }

        $user = DB::transaction(function () use ($data, $role, $actor): User {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->is_admin = true;
            $user->status = AdminAccountStatus::Invited;
            $user->role_id = $role->getKey();
            $user->save();

            $this->audit->record($user, AuditAction::AdminInvited, $actor, [
                'email' => $user->email,
                'role' => $role->name,
            ]);

            return $user;
        });

        // Outside the transaction on purpose: a mail outage must not undo the
        // account, and the invitation can be reissued from the account list.
        $invitation = $this->invitations->issue($user, $actor);
        $sent = $this->invitations->send($invitation);

        return redirect()
            ->route('admin.accounts.index')
            ->with(
                $sent ? 'success' : 'warning',
                $sent
                    ? "Undangan dikirim ke {$user->email}. Tautan berlaku ".AdminInvitationService::LIFETIME_HOURS.' jam.'
                    : "Akun {$user->email} dibuat, tetapi email undangan gagal dikirim. Gunakan \"Kirim ulang undangan\".",
            );
    }

    /**
     * Replace the pending invitation with a fresh 24-hour link.
     */
    public function resendInvitation(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin-accounts.manage');
        $this->ensureIsAdminAccount($user);

        $actor = $request->user();

        if (! $user->isInvited()) {
            return back()->with('error', 'Undangan hanya dapat dikirim ulang untuk akun yang belum aktif.');
        }

        // Mirrors the rule applied at creation time.
        if ($user->isSuperAdmin() && ! $actor->isSuperAdmin()) {
            return back()->with('error', 'Hanya Super Admin yang dapat mengirim ulang undangan Super Admin.');
        }

        $invitation = $this->invitations->issue($user, $actor);
        $sent = $this->invitations->send($invitation);

        $this->audit->record($user, AuditAction::AdminInvitationReissued, $actor, [
            'email' => $user->email,
            'delivered' => $sent,
        ]);

        return back()->with(
            $sent ? 'success' : 'warning',
            $sent
                ? "Undangan baru dikirim ke {$user->email}. Tautan sebelumnya tidak berlaku lagi."
                : 'Email undangan gagal dikirim. Periksa konfigurasi email lalu coba lagi.',
        );
    }

    /**
     * Revoke access without deleting the account.
     */
    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin-accounts.manage');
        $this->ensureIsAdminAccount($user);

        $actor = $request->user();

        if ($actor->is($user)) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }

        if (! $user->isActive()) {
            return back()->with('error', "Akun {$user->name} tidak dalam status aktif.");
        }

        // Without this check the only Super Admin could deactivate themselves
        // out of the panel and leave nobody able to restore access.
        if ($user->isSuperAdmin() && $this->otherActiveSuperAdmins($user) === 0) {
            return back()->with('error', 'Super Admin terakhir tidak dapat dinonaktifkan.');
        }

        DB::transaction(function () use ($user, $actor): void {
            $user->status = AdminAccountStatus::Inactive;
            $user->deactivated_at = now();
            $user->save();

            // An open invitation would let the account come back on its own.
            $this->invitations->revokePending($user);

            $this->revokeSessions($user);

            $this->audit->record($user, AuditAction::AdminDeactivated, $actor, [
                'email' => $user->email,
                'role' => $user->role?->name,
            ]);
        });

        return back()->with('success', "Akun {$user->name} dinonaktifkan dan sesinya dihentikan.");
    }

    /**
     * Restore access for a previously deactivated account.
     */
    public function activate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin-accounts.manage');
        $this->ensureIsAdminAccount($user);

        if ($user->isInvited()) {
            return back()->with('error', 'Akun ini masih menunggu undangan. Kirim ulang undangan agar pemiliknya dapat membuat password.');
        }

        if ($user->isActive()) {
            return back()->with('error', "Akun {$user->name} sudah aktif.");
        }

        DB::transaction(function () use ($user, $request): void {
            $user->status = AdminAccountStatus::Active;
            $user->deactivated_at = null;
            $user->save();

            $this->audit->record($user, AuditAction::AdminActivated, $request->user(), [
                'email' => $user->email,
                'role' => $user->role?->name,
            ]);
        });

        return back()->with('success', "Akun {$user->name} diaktifkan kembali.");
    }

    /**
     * Roles an actor is allowed to hand out.
     *
     * @return Collection<int, Role>
     */
    private function assignableRoles(User $actor): Collection
    {
        return Role::query()
            ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('is_super_admin', false))
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->get();
    }

    /**
     * Account routes only ever target admin accounts; an arbitrary user id must
     * look like it does not exist.
     */
    private function ensureIsAdminAccount(User $user): void
    {
        abort_unless($user->isAdmin(), 404);
    }

    /**
     * Active Super Admins other than $user, i.e. everyone who could still
     * restore access if this account were deactivated.
     */
    private function otherActiveSuperAdmins(User $user): int
    {
        return User::query()
            ->whereKeyNot($user->getKey())
            ->where('is_admin', true)
            ->where('status', AdminAccountStatus::Active->value)
            ->whereHas('role', fn ($query) => $query->where('is_super_admin', true))
            ->count();
    }

    /**
     * End every session belonging to the account.
     *
     * The project uses the database session driver, so deleting the rows logs
     * the admin out on their next request. The admin middleware also re-checks
     * the account status on every request, so a session stored elsewhere is
     * still refused.
     */
    private function revokeSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }
}
