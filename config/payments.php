<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment proof upload constraints
    |--------------------------------------------------------------------------
    |
    | PRD FR-06 requires file type and size to be validated but does not fix
    | the limits (PRD section 15 question 10 is still open). These values are
    | recorded as an assumption, not a confirmed decision.
    |
    | JPG, PNG, and WebP are screenshots and photos of a transfer receipt,
    | which is what participants actually send. PDF is deliberately excluded:
    | it can carry active content, and allowing it would mean an admin opens
    | an untrusted document from a stranger.
    |
    | Keep `max_kilobytes` in sync with the web server's upload_max_filesize;
    | a limit larger than PHP's own produces a confusing empty request rather
    | than a validation error.
    |
    */

    'proof' => [
        'max_kilobytes' => 4096,
        'mimes' => ['jpg', 'jpeg', 'png', 'webp'],

        // Private disk. FILESYSTEM_DISK defaults to `local`, whose root is
        // storage/app/private, so proofs are never reachable over HTTP and
        // are only streamed by an authorised controller.
        'disk' => 'local',
        'directory' => 'payment-proofs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the methods offered and the instructions
    | shown (PRD FR-05: "Instruksi disimpan sebagai konfigurasi, bukan
    | tersebar di kode").
    |
    | NOTHING HERE IS REAL. The active method list and the account, VA, and
    | QRIS merchant details are unanswered business questions (PRD section 15
    | questions 1 and 2), so no account number has been invented. Every entry
    | below is development scaffolding, flagged by `is_dummy`, and
    | PaymentMethodRegistry refuses to serve dummy methods outside local and
    | testing environments. Phase 05C replaces these with official data.
    |
    | Fields:
    |   label        Shown in the method picker.
    |   is_active    Offered to participants at all.
    |   is_dummy     Development placeholder; blocked in production.
    |   account      Recipient rows rendered as a definition list.
    |   steps        Ordered transfer instructions.
    |
    | `scope` records PRD section 15 question 16 (global vs per-event). Until
    | that is answered the implementation reads one global list; the key is
    | here so the intent is explicit rather than silently assumed.
    |
    */

    'scope' => 'global',

    'methods' => [

        'dev_bank_transfer' => [
            'label' => '[CONTOH] Transfer Bank',
            'is_active' => true,
            'is_dummy' => true,
            'account' => [
                'Bank' => 'CONTOH — bukan bank sebenarnya',
                'Nomor Rekening' => '0000000000 (CONTOH)',
                'Atas Nama' => 'CONTOH — data belum dikonfirmasi',
            ],
            'steps' => [
                'Data pada metode ini hanya contoh untuk pengembangan.',
                'Jangan melakukan transfer ke nomor di atas.',
                'Instruksi sebenarnya menunggu konfirmasi pemilik bisnis.',
            ],
        ],

        'dev_ewallet' => [
            'label' => '[CONTOH] E-Wallet',
            'is_active' => true,
            'is_dummy' => true,
            'account' => [
                'Penyedia' => 'CONTOH — penyedia belum ditentukan',
                'Nomor Tujuan' => '0000000000 (CONTOH)',
                'Atas Nama' => 'CONTOH — data belum dikonfirmasi',
            ],
            'steps' => [
                'Data pada metode ini hanya contoh untuk pengembangan.',
                'Jangan melakukan transfer ke nomor di atas.',
                'Instruksi sebenarnya menunggu konfirmasi pemilik bisnis.',
            ],
        ],

    ],

];
