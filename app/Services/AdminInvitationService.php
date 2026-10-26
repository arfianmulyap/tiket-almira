<?php

namespace App\Services;

use App\Enums\EmailDeliveryStatus;
use App\Mail\AdminInvitationMail;
use App\Models\AdminInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues, reissues, and delivers admin invitations (Phase 11).
 *
 * Tokens are single use and live for 24 hours. Only the SHA-256 hash is stored;
 * the plaintext exists on the model instance long enough to build the email and
 * is never written to the database or the audit trail.
 *
 * Delivery is synchronous and never throws: an unreachable mail server must not
 * undo the account creation or the invitation row, or the admin would have to
 * start over. The failure is logged and the invitation can be reissued from the
 * account list, which is also what Phase 13 will build its retry status on.
 */
class AdminInvitationService
{
    /**
     * How long an invitation link stays valid.
     */
    public const LIFETIME_HOURS = 24;

    public function __construct(private readonly EmailDeliveryLogger $deliveries) {}

    /**
     * Revoke any outstanding invitation and create a fresh one.
     *
     * Reissuing invalidates the previous link, which is the intended behaviour
     * when an email was lost: two live links for one account would be two
     * chances to take it over.
     */
    public function issue(User $user, ?User $inviter = null): AdminInvitation
    {
        $this->revokePending($user);

        $token = Str::random(64);

        $invitation = new AdminInvitation;
        $invitation->user_id = $user->getKey();
        $invitation->email = $user->email;
        $invitation->role_id = $user->role_id;
        $invitation->invited_by = $inviter?->getKey();
        $invitation->token_hash = $this->hash($token);
        $invitation->expires_at = now()->addHours(self::LIFETIME_HOURS);
        $invitation->save();

        // Kept in memory only, so send() can build the signed link.
        $invitation->plainToken = $token;
        $invitation->setRelation('user', $user);

        return $invitation;
    }

    /**
     * Mark every still-open invitation of the account as revoked.
     */
    public function revokePending(User $user): int
    {
        return AdminInvitation::query()
            ->where('user_id', $user->getKey())
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * Resolve a token from the invitation link.
     *
     * Returns null for anything that must not be honoured: unknown, expired,
     * revoked, already accepted, or belonging to an account that is no longer
     * waiting for activation. The caller shows one message for all of them, so
     * a wrong token cannot be used to probe which accounts exist.
     */
    public function findUsable(string $token): ?AdminInvitation
    {
        if ($token === '') {
            return null;
        }

        $invitation = AdminInvitation::query()
            ->where('token_hash', $this->hash($token))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with(['user.role', 'role'])
            ->first();

        if ($invitation === null) {
            return null;
        }

        $user = $invitation->user;

        if ($user === null || ! $user->isAdmin() || ! $user->isInvited()) {
            return null;
        }

        return $invitation;
    }

    /**
     * Attempt delivery. Returns true only if the transport accepted the mail.
     *
     * Phase 13 records every attempt in `email_deliveries`, so the account list
     * can show whether the person actually received a link and a failed send can
     * be retried with a fresh token from the same screen.
     */
    public function send(AdminInvitation $invitation): bool
    {
        if ($invitation->plainToken === null) {
            return false;
        }

        $actor = $invitation->invitedBy;

        try {
            Mail::send(new AdminInvitationMail($invitation, $invitation->plainToken));

            $this->deliveries->record(
                $invitation,
                $invitation->email,
                EmailDeliveryStatus::Sent,
                $actor,
            );

            return true;
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim undangan admin.', [
                'user_id' => $invitation->user_id,
                'email' => $invitation->email,
                'exception' => $exception->getMessage(),
            ]);

            $this->deliveries->record(
                $invitation,
                $invitation->email,
                EmailDeliveryStatus::Failed,
                $actor,
                $exception->getMessage(),
            );

            return false;
        }
    }

    /**
     * Hash of the token as stored in `admin_invitations.token_hash`.
     */
    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
