<?php

namespace App\Mail;

use App\Models\AdminInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation email for a new admin account (Phase 11).
 *
 * The link carries the only copy of the one-time token; the database keeps just
 * its hash. Phase 13 owns delivery status and retry bookkeeping, so this class
 * stays a plain synchronous mailable.
 */
class AdminInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly AdminInvitation $invitation,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->invitation->email],
            subject: 'Undangan admin '.config('app.name'),
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['user', 'role', 'invitedBy']);

        return new Content(
            view: 'mail.admin-invitation',
            with: [
                'name' => $this->invitation->user?->name,
                'roleName' => $this->invitation->role?->name,
                'inviterName' => $this->invitation->invitedBy?->name,
                'acceptUrl' => route('invitations.accept', $this->token),
                'expiresAt' => $this->invitation->expires_at,
            ],
        );
    }
}
