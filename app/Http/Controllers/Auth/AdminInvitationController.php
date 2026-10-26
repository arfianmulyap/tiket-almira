<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AdminAccountStatus;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptAdminInvitationRequest;
use App\Models\AdminInvitation;
use App\Services\AdminInvitationService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Accepting an admin invitation (Phase 11).
 *
 * The token is the only credential: the recipient has no password yet, so these
 * actions are open to guests. Everything the acceptance writes — the password,
 * the `active` status, the accepted timestamp — comes from the invitation row
 * the token resolves to, never from the form.
 */
class AdminInvitationController extends Controller
{
    public function __construct(
        private readonly AdminInvitationService $invitations,
        private readonly AuditLogger $audit,
    ) {}

    public function show(string $token): View
    {
        return view('auth.invitation', [
            'token' => $token,
            // Null renders the "link is no longer valid" state instead of a
            // form that would only fail on submit.
            'invitation' => $this->invitations->findUsable($token),
        ]);
    }

    public function store(AcceptAdminInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->findUsable($token);

        // Re-checked here rather than trusted from the form: the token may have
        // expired between opening the page and submitting it.
        if ($invitation === null || $invitation->user === null) {
            return redirect()
                ->route('invitations.accept', $token)
                ->with('error', 'Undangan tidak valid, sudah dipakai, atau sudah kedaluwarsa. Minta Super Admin mengirim undangan baru.');
        }

        $user = $invitation->user;

        DB::transaction(function () use ($request, $invitation, $user): void {
            $user->password = (string) $request->validated('password');
            $user->status = AdminAccountStatus::Active;
            $user->deactivated_at = null;

            // Receiving the email and clicking the link proves control of the
            // mailbox the account is registered to.
            $user->email_verified_at ??= now();
            $user->save();

            $invitation->accepted_at = now();
            $invitation->save();

            // Any other open link for this account is pointless now.
            AdminInvitation::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($invitation->getKey())
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            // The recipient acts on their own account, so they are the actor in
            // the trail. Who invited them is on the invitation row.
            $this->audit->record($user, AuditAction::AdminInvitationAccepted, $user, [
                'email' => $user->email,
                'invited_by' => $invitation->invited_by,
            ]);
        });

        return redirect()
            ->route('login')
            ->with('success', 'Akun Anda sudah aktif. Silakan masuk dengan password yang baru dibuat.');
    }
}
