<?php

namespace App\Enums;

/**
 * The kind of manual payment a method represents (Phase 12B).
 *
 * Phase 12 kept one generic list of detail rows per method, which is enough to
 * display instructions but not enough to validate them: nothing stopped a bank
 * transfer from being saved without an account number, or a QRIS method from
 * being saved without a QR code. Phase 12B requires the required fields to
 * depend on the selected type, which is why the type is a stored attribute and
 * why the field specification lives here rather than in a Blade template.
 *
 * This enum is the single source of truth for three things that must never
 * disagree:
 *
 *  1. which detail fields the admin form renders, and their labels;
 *  2. which of those fields a save must reject when they are empty;
 *  3. how a stored detail row is mapped back to a field, which is what allows
 *     the edit form to be pre-filled — including for rows written by Phase 12
 *     with a hand-typed label.
 *
 * Nothing here is a payment detail. The enum names the *shape* of a payment
 * method; the values themselves are configuration stored in the database, in
 * line with PRD FR-05 ("instruksi disimpan sebagai konfigurasi").
 *
 * `merchant` and `merchant_id` exist only for QRIS. The merchant reference may
 * stand in for the QR image, because a business may hand over an NMID before a
 * usable image — but never both silently missing, or the participant is told to
 * scan something that is not there.
 */
enum PaymentMethodType: string
{
    case BankTransfer = 'bank_transfer';
    case VirtualAccount = 'virtual_account';
    case EWallet = 'ewallet';
    case Qris = 'qris';

    /**
     * Name shown in the admin list, the method picker, and the type selector.
     */
    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Transfer Bank',
            self::VirtualAccount => 'Virtual Account',
            self::EWallet => 'E-Wallet',
            self::Qris => 'QRIS',
        };
    }

    /**
     * One-line explanation, so the type can be chosen without guessing.
     */
    public function description(): string
    {
        return match ($this) {
            self::BankTransfer => 'Peserta mentransfer ke rekening bank penerima.',
            self::VirtualAccount => 'Peserta mentransfer ke nomor Virtual Account.',
            self::EWallet => 'Peserta mentransfer ke dompet digital (e-wallet).',
            self::Qris => 'Peserta memindai kode QR merchant.',
        };
    }

    /**
     * Detail fields this type uses, in the order they are shown.
     *
     * The array key is the stable field name submitted by the form and stored
     * as the row's identity; `label` is what a participant reads. `aliases`
     * are the labels Phase 12 admins may have typed by hand, and they are what
     * lets an existing row be recognised instead of silently discarded.
     *
     * @return array<string, array{
     *     label: string,
     *     required: bool,
     *     placeholder: string,
     *     hint: string|null,
     *     aliases: array<int, string>
     * }>
     */
    public function details(): array
    {
        return match ($this) {
            self::BankTransfer => [
                'provider' => self::field('Bank', true, 'Contoh: BSI', 'Nama bank penerima.', ['Bank', 'Penerbit', 'Penyedia']),
                'number' => self::field('Nomor Rekening', true, 'Contoh: 1234567890', 'Tulis lengkap tanpa spasi atau tanda hubung.', ['Nomor Rekening', 'No. Rekening', 'No Rekening', 'Nomor']),
                'name' => self::field('Atas Nama', true, 'Nama pemilik rekening', null, ['Atas Nama', 'Nama Pemilik', 'Pemilik Rekening', 'Nama']),
            ],

            self::VirtualAccount => [
                'provider' => self::field('Bank Penerbit', true, 'Contoh: Mandiri', 'Bank yang menerbitkan nomor VA.', ['Bank Penerbit', 'Bank', 'Penerbit', 'Penyedia']),
                'number' => self::field('Nomor VA', true, 'Contoh: 8880812345678901', 'Nomor Virtual Account, bukan nomor rekening biasa.', ['Nomor VA', 'No VA', 'Nomor Virtual Account', 'Virtual Account', 'Nomor']),
                'name' => self::field('Atas Nama', true, 'Nama pemilik VA', null, ['Atas Nama', 'Nama Pemilik', 'Pemilik', 'Nama']),
            ],

            self::EWallet => [
                'provider' => self::field('Penyedia', true, 'Contoh: GoPay', 'Nama layanan dompet digital.', ['Penyedia', 'E-Wallet', 'Dompet Digital', 'Penerbit', 'Bank']),
                'number' => self::field('Nomor Tujuan', true, 'Contoh: 081234567890', 'Nomor yang terdaftar pada dompet digital.', ['Nomor Tujuan', 'Nomor', 'Nomor HP', 'Nomor Telepon']),
                'name' => self::field('Atas Nama', true, 'Nama pemilik akun', null, ['Atas Nama', 'Nama Pemilik', 'Pemilik', 'Nama']),
            ],

            self::Qris => [
                'merchant' => self::field('Merchant', true, 'Contoh: Almira Event', 'Nama merchant yang tampil di aplikasi pembayaran peserta.', ['Merchant', 'Nama Merchant', 'Merchant QRIS', 'Nama Usaha']),
                'provider' => self::field('Penyedia QRIS', false, 'Contoh: Midtrans', 'Penyedia atau bank penerbit QRIS, bila diketahui.', ['Penyedia QRIS', 'Penyedia', 'Penerbit', 'Bank']),
                'merchant_id' => self::field('ID Merchant / NMID', false, 'Contoh: ID1022345678', 'Boleh dikosongkan bila gambar QR sudah diunggah.', ['ID Merchant', 'ID Merchant / NMID', 'NMID', 'Merchant ID']),
            ],
        };
    }

    /**
     * Whether a QR image belongs to this type.
     *
     * True only for QRIS: for every other type an image would be decoration,
     * and demanding one would block a legitimate method. A QRIS method may
     * still be saved with a merchant reference instead of an image; that rule
     * is enforced alongside the upload in the form request, because it depends
     * on request input.
     */
    public function usesQrImage(): bool
    {
        return $this === self::Qris;
    }

    /**
     * Field whose value may stand in for a missing QR image.
     */
    public function merchantReferenceField(): ?string
    {
        return $this === self::Qris ? 'merchant_id' : null;
    }

    /**
     * Canonical label a field is stored under for this type, or null when the
     * type does not use that field.
     */
    public function labelFor(string $field): ?string
    {
        return $this->details()[$field]['label'] ?? null;
    }

    /**
     * Which field a stored detail row belongs to, looking through every type's
     * canonical label and aliases.
     *
     * Returns null for a row that matches nothing, which is how Phase 12 rows
     * with an admin-invented label are detected and preserved rather than
     * dropped during an edit.
     */
    public static function fieldForLabel(string $label): ?string
    {
        $needle = self::normaliseLabel($label);

        if ($needle === '') {
            return null;
        }

        foreach (self::cases() as $type) {
            foreach ($type->details() as $field => $spec) {
                foreach ([$spec['label'], ...$spec['aliases']] as $candidate) {
                    if (self::normaliseLabel($candidate) === $needle) {
                        return $field;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Normalised label for comparison: case and surrounding whitespace must not
     * decide whether two labels mean the same field.
     */
    private static function normaliseLabel(string $label): string
    {
        return mb_strtolower(trim($label));
    }

    /**
     * @param  array<int, string>  $aliases
     * @return array{label: string, required: bool, placeholder: string, hint: string|null, aliases: array<int, string>}
     */
    private static function field(
        string $label,
        bool $required,
        string $placeholder,
        ?string $hint,
        array $aliases = [],
    ): array {
        return [
            'label' => $label,
            'required' => $required,
            'placeholder' => $placeholder,
            'hint' => $hint,
            'aliases' => $aliases,
        ];
    }
}
