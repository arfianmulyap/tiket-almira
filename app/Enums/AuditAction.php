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

    // Phase 10: every change to who may do what is recorded, so an access
    // change can always be traced back to the admin who made it.
    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';
    case RolePermissionsChanged = 'role.permissions_changed';
    case PermissionCreated = 'permission.created';
    case PermissionUpdated = 'permission.updated';
    case PermissionDeleted = 'permission.deleted';

    // Phase 11: who was invited, activated, or cut off — and by whom. Account
    // changes are as sensitive as permission changes, so they belong in the
    // same trail.
    case AdminInvited = 'admin.invited';
    case AdminInvitationReissued = 'admin.invitation_reissued';
    case AdminInvitationAccepted = 'admin.invitation_accepted';
    case AdminActivated = 'admin.activated';
    case AdminDeactivated = 'admin.deactivated';
    case AdminProfileUpdated = 'admin.profile_updated';
    case AdminPasswordUpdated = 'admin.password_updated';

    // Phase 12: payment-method configuration changes. Account numbers and
    // QRIS details decide where participants send money, so every edit is
    // traced to the admin who made it.
    case PaymentMethodUpdated = 'payment.method_updated';
    case PaymentMethodActivated = 'payment.method_activated';
    case PaymentMethodDeactivated = 'payment.method_deactivated';

    // Phase 13: a manual resend of a participant's e-ticket. The automatic
    // attempt made during approval lives in `email_deliveries`; this records
    // who decided to send the ticket again.
    case TicketEmailResent = 'ticket.email_resent';

    public function label(): string
    {
        return match ($this) {
            self::PaymentProofSubmitted => 'Bukti pembayaran dikirim',
            self::PaymentApproved => 'Pembayaran disetujui',
            self::PaymentRejected => 'Pembayaran ditolak',
            self::TicketIssued => 'Tiket diterbitkan',
            self::TicketCheckedIn => 'Peserta check-in',
            self::OrderExpired => 'Pesanan kedaluwarsa',
            self::RoleCreated => 'Role dibuat',
            self::RoleUpdated => 'Role diubah',
            self::RoleDeleted => 'Role dihapus',
            self::RolePermissionsChanged => 'Permission role diubah',
            self::PermissionCreated => 'Permission dipulihkan',
            self::PermissionUpdated => 'Permission diubah',
            self::PermissionDeleted => 'Permission dihapus',
            self::AdminInvited => 'Admin diundang',
            self::AdminInvitationReissued => 'Undangan admin dikirim ulang',
            self::AdminInvitationAccepted => 'Undangan admin diterima',
            self::AdminActivated => 'Akun admin diaktifkan',
            self::AdminDeactivated => 'Akun admin dinonaktifkan',
            self::AdminProfileUpdated => 'Profil admin diubah',
            self::AdminPasswordUpdated => 'Password admin diubah',
            self::PaymentMethodUpdated => 'Metode pembayaran diubah',
            self::PaymentMethodActivated => 'Metode pembayaran diaktifkan',
            self::PaymentMethodDeactivated => 'Metode pembayaran dinonaktifkan',
            self::TicketEmailResent => 'E-ticket dikirim ulang',
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
