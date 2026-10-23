<?php

namespace App\Enums;

/**
 * Operationally important actions recorded in the audit trail (PRD FR-12).
 *
 * Stored as a plain string column rather than a database enum so Phase 08 can
 * add check-in actions without a schema migration.
 */
enum AuditAction: string
{
    case PaymentProofSubmitted = 'payment.proof_submitted';
    case PaymentApproved = 'payment.approved';
    case PaymentRejected = 'payment.rejected';
    case TicketIssued = 'ticket.issued';
    case TicketCheckedIn = 'ticket.checked_in';
    case OrderExpired = 'order.expired';

    public function label(): string
    {
        return match ($this) {
            self::PaymentProofSubmitted => 'Bukti pembayaran dikirim',
            self::PaymentApproved => 'Pembayaran disetujui',
            self::PaymentRejected => 'Pembayaran ditolak',
            self::TicketIssued => 'Tiket diterbitkan',
            self::TicketCheckedIn => 'Peserta check-in',
            self::OrderExpired => 'Pesanan kedaluwarsa',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
