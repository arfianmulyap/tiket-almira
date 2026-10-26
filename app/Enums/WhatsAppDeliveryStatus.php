<?php

namespace App\Enums;

/**
 * Outcome of one WhatsApp reminder delivery attempt (Phase 14).
 *
 * Unlike the email trail, a row here is not append-only: it represents one
 * reminder stage for one order and is updated as retries happen. Uniqueness of
 * `(order_id, kind)` is what prevents the same stage from being queued twice.
 *
 * SKIPPED is distinct from FAILED for the same reason as in the email trail: a
 * reminder that is deliberately not sent because the order already has a proof,
 * has expired, or was superseded by the next stage is not an error an operator
 * should chase.
 */
enum WhatsAppDeliveryStatus: string
{
    case Pending = 'PENDING';
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Skipped = 'SKIPPED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Sent => 'Terkirim',
            self::Failed => 'Gagal dikirim',
            self::Skipped => 'Dilewati',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Skipped => 'neutral',
        };
    }

    /**
     * Whether the participant can be expected to have received the message.
     */
    public function isDelivered(): bool
    {
        return $this === self::Sent;
    }
}
