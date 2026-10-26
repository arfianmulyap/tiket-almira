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

        // Private disk. Defaults to `local`, whose root is
        // storage/app/private, so proofs are never reachable over HTTP and
        // are only streamed by an authorised controller.
        //
        // Env-driven because a serverless host has no persistent local
        // filesystem: on Vercel the only writable path is /tmp and it is
        // discarded when the function instance ends, which would lose a proof
        // that OPERATIONS.md records as impossible to recreate. Set
        // PAYMENT_PROOF_DISK=s3_private there. Whatever the value, the disk
        // must stay private — a proof must never be readable by URL.
        'disk' => env('PAYMENT_PROOF_DISK', 'local'),
        'directory' => 'payment-proofs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment methods
    |--------------------------------------------------------------------------
    |
    | Seed catalog for the `payment_methods` table (Phase 12).
    |
    | Runtime reads the table, not this file, so an authorised admin can
    | toggle a method or maintain its recipient details without a deployment
    | (PRD FR-05: "Instruksi disimpan sebagai konfigurasi, bukan tersebar di
    | kode"). PaymentMethodSeeder copies the entries below into the table
    | once; afterwards this file is only consulted to introduce a brand-new
    | method.
    |
    | NOTHING HERE IS REAL. The active method list and the account, VA, and
    | QRIS merchant details are unanswered business questions (PRD section 15
    | questions 1 and 2), so no account number has been invented. Every entry
    | below is development scaffolding, flagged by `is_dummy`, and
    | PaymentMethodRegistry refuses to serve dummy methods outside local and
    | testing environments. A Super Admin replaces them with official data on
    | the payment settings screen and then clears the dummy flag.
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

    /*
    |--------------------------------------------------------------------------
    | Allow placeholder methods outside local and testing
    |--------------------------------------------------------------------------
    |
    | PaymentMethodRegistry hides dummy methods outside local and testing so
    | placeholder account numbers can never be shown to a participant as a real
    | instruction. That is the right default and must stay the default.
    |
    | This flag is a deliberate escape hatch for an internal deployment that
    | nobody outside the team can reach, where the point is to exercise the
    | ordering and upload flow end to end. There is nothing to exercise with no
    | selectable method.
    |
    | It does not suppress the visible "Mode pengembangan" warning, so the page
    | still states plainly that the data is an example and no transfer should be
    | made. Set PAYMENTS_ALLOW_DUMMY=false before real participants are given
    | the URL; Phase 05C replaces the entries below with confirmed data and
    | makes the flag unnecessary.
    |
    */

    'allow_dummy' => env('PAYMENTS_ALLOW_DUMMY', false),

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
