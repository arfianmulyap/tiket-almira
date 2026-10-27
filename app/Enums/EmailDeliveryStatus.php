<?php

namespace App\Enums;

/**
 * Outcome of one attempt to deliver a transactional email (Phase 13).
 *
 * The three cases are deliberately distinct. A failed attempt is an
 * operational problem an admin must retry, while a skipped attempt only means
 * delivery is switched off for this environment (TICKET_EMAIL_ENABLED) or that
 * no mail service has been confirmed yet (PRD section 15 question 7).
 * Collapsing them would show "gagal dikirim" on every order of an installation
 * that simply has email turned off, and operators would learn to ignore it.
 */
enum EmailDeliveryStatus: string
{
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal dikirim',
            self::Skipped => 'Dilewati',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }

    /**
     * Whether the participant can be expected to have the email.
     */
    public function isDelivered(): bool
    {
        return $this === self::Sent;
    }
}
