<?php

namespace App\Enums;

/**
 * Lifecycle of an order, following PRD section 9.
 *
 * Order and payment status are kept in one column for the MVP: one order
 * carries exactly one ticket and one payment, so a separate payment status
 * would always move in lockstep with this one.
 */
enum OrderStatus: string
{
    case PendingPayment = 'PENDING_PAYMENT';
    case PendingVerification = 'PENDING_VERIFICATION';
    case Paid = 'PAID';
    case Rejected = 'REJECTED';
    case Expired = 'EXPIRED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Menunggu Pembayaran',
            self::PendingVerification => 'Menunggu Verifikasi',
            self::Paid => 'Pembayaran Disetujui',
            self::Rejected => 'Pembayaran Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::PendingVerification => 'info',
            self::Paid => 'success',
            self::Rejected, self::Expired, self::Cancelled => 'neutral',
        };
    }

    /**
     * Whether an order in this status still holds a quota slot.
     *
     * PRD FR-08: a slot is held from creation, stays held while the proof
     * waits for review, and is kept once the payment is approved. Rejected,
     * expired, and cancelled orders release their slot.
     *
     * PENDING_PAYMENT holds a slot only until its deadline passes; see
     * Event::reservedQuota(), which also applies the time check.
     */
    public function holdsQuota(): bool
    {
        return match ($this) {
            self::PendingPayment, self::PendingVerification, self::Paid => true,
            self::Rejected, self::Expired, self::Cancelled => false,
        };
    }

    /**
     * Statuses that permanently hold a slot regardless of the deadline.
     *
     * @return array<int, self>
     */
    public static function holdingQuotaUnconditionally(): array
    {
        return [self::PendingVerification, self::Paid];
    }

    /**
     * Whether the participant may still submit a payment proof.
     */
    public function awaitsProof(): bool
    {
        return $this === self::PendingPayment;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
