<?php

namespace App\Enums;

/**
 * Catalog of permissions enforced by the admin panel (Phase 10).
 *
 * The `permissions` table mirrors this list so roles can be granted access
 * through the UI, but this enum stays the source of truth for the key, label,
 * and group. That way a typo cannot create a permission no backend action ever
 * checks, and every case here must be consulted somewhere in the backend.
 */
enum AdminPermission: string
{
    case DashboardView = 'dashboard.view';
    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case OrdersView = 'orders.view';
    case PaymentProofView = 'payment.proof.view';
    case PaymentVerify = 'payment.verify';

    // Phase 12: the payment settings screen. Phase 12B splits the original
    // "manage" permission into four, because creating a method, editing global
    // details, toggling activation, and deleting are materially different
    // actions: a role that may toggle a method for one event must not thereby
    // be allowed to delete it or rewrite a bank account. Publishing placeholder
    // details as official (clearing `is_dummy`) stays Super Admin only and is
    // enforced by a policy rather than by a permission that could be granted to
    // a custom role.
    case PaymentMethodsView = 'payment_methods.view';
    case PaymentMethodsCreate = 'payment_methods.create';
    case PaymentMethodsUpdate = 'payment_methods.update';
    case PaymentMethodsToggle = 'payment_methods.toggle';
    case PaymentMethodsDelete = 'payment_methods.delete';

    case CheckInPerform = 'check-in.perform';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case PermissionsManage = 'permissions.manage';

    // Phase 11: account management is separate from role management, so an
    // admin can be allowed to handle people without being allowed to change
    // what those people are able to do.
    case AdminAccountsView = 'admin-accounts.view';
    case AdminAccountsManage = 'admin-accounts.manage';

    // Phase 13: resending an e-ticket mails a participant's personal ticket.
    // That is deliberately its own permission, not implied by `orders.view`:
    // reading an order is not the same as being trusted to send something to
    // the address on it.
    case TicketsResend = 'tickets.resend';

    /**
     * Human readable name shown in the permission matrix.
     */
    public function label(): string
    {
        return match ($this) {
            self::DashboardView => 'Lihat dashboard',
            self::EventsView => 'Lihat event',
            self::EventsCreate => 'Buat event',
            self::EventsUpdate => 'Ubah event',
            self::OrdersView => 'Lihat pesanan',
            self::PaymentProofView => 'Lihat bukti pembayaran',
            self::PaymentVerify => 'Setujui / tolak pembayaran',
            self::PaymentMethodsView => 'Lihat metode pembayaran',
            self::PaymentMethodsCreate => 'Tambah metode pembayaran',
            self::PaymentMethodsUpdate => 'Ubah detail metode pembayaran',
            self::PaymentMethodsToggle => 'Aktifkan / nonaktifkan metode pembayaran',
            self::PaymentMethodsDelete => 'Hapus metode pembayaran',
            self::CheckInPerform => 'Check-in peserta',
            self::RolesView => 'Lihat role',
            self::RolesManage => 'Kelola role',
            self::PermissionsManage => 'Kelola permission',
            self::AdminAccountsView => 'Lihat akun admin',
            self::AdminAccountsManage => 'Kelola akun admin',
            self::TicketsResend => 'Kirim ulang e-ticket',
        };
    }

    /**
     * Section the permission belongs to in the matrix and the list screen.
     */
    public function group(): string
    {
        return match ($this) {
            self::DashboardView => 'Dashboard',
            self::EventsView, self::EventsCreate, self::EventsUpdate => 'Event',
            self::OrdersView => 'Pesanan',
            self::PaymentProofView, self::PaymentVerify, self::PaymentMethodsView, self::PaymentMethodsCreate, self::PaymentMethodsUpdate, self::PaymentMethodsToggle, self::PaymentMethodsDelete => 'Pembayaran',
            self::CheckInPerform => 'Check-in',
            self::RolesView, self::RolesManage, self::PermissionsManage => 'Hak Akses',
            self::AdminAccountsView, self::AdminAccountsManage => 'Akun Admin',
            self::TicketsResend => 'Tiket',
        };
    }

    /**
     * Explain what the permission actually allows, so a role can be built
     * without guessing from the label.
     */
    public function description(): string
    {
        return match ($this) {
            self::DashboardView => 'Membuka dashboard ringkasan operasional.',
            self::EventsView => 'Membuka daftar dan detail event.',
            self::EventsCreate => 'Membuat event baru beserta posternya.',
            self::EventsUpdate => 'Mengubah data, harga, kuota, dan status event.',
            self::OrdersView => 'Membuka daftar dan detail pesanan peserta.',
            self::PaymentProofView => 'Membuka berkas bukti pembayaran peserta.',
            self::PaymentVerify => 'Menyetujui atau menolak pembayaran dan menerbitkan tiket.',
            self::PaymentMethodsView => 'Membuka daftar metode pembayaran beserta status, tipe, dan detailnya.',
            self::PaymentMethodsCreate => 'Menambah metode pembayaran baru beserta detail dan gambarnya.',
            self::PaymentMethodsUpdate => 'Mengubah nama, tipe, detail penerima, dan langkah pembayaran sebuah metode.',
            self::PaymentMethodsToggle => 'Mengaktifkan atau menonaktifkan metode pembayaran, baik secara global maupun untuk satu event.',
            self::PaymentMethodsDelete => 'Menghapus metode pembayaran yang tidak dipakai pesanan atau pembayaran mana pun.',
            self::CheckInPerform => 'Membuka scanner dan melakukan check-in peserta.',
            self::RolesView => 'Membuka daftar role beserta permission-nya.',
            self::RolesManage => 'Membuat, mengubah, menghapus role, dan mengatur permission-nya.',
            self::PermissionsManage => 'Memulihkan, mengubah, dan menghapus definisi permission.',
            self::AdminAccountsView => 'Membuka daftar akun admin beserta role dan statusnya.',
            self::AdminAccountsManage => 'Mengundang admin baru, mengirim ulang undangan, serta menonaktifkan dan mengaktifkan akun.',
            self::TicketsResend => 'Mengirim ulang email e-ticket beserta lampiran PDF untuk pesanan yang pembayarannya sudah disetujui.',
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
