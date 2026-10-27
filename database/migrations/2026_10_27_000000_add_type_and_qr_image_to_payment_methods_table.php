<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payment type and QR merchant asset on a payment method (Phase 12B).
 *
 * Phase 12 stored one generic ordered list of detail rows per method, which is
 * enough to *display* instructions but not enough to *validate* them: a bank
 * transfer without an account number and a QRIS method without a QR image were
 * both accepted. Phase 12B requires the required fields to depend on the
 * selected type (PRD FR-05, "instruksi disimpan sebagai konfigurasi"), so the
 * type has to be a stored attribute rather than something inferred from the
 * labels of the detail rows.
 *
 * `qr_image_path` is the "QR merchant image or file reference" of scope B. It
 * sits on the publicly readable disk because a participant has to see the code
 * in order to scan it, exactly like an event poster.
 *
 * Both columns are additive and nullable-safe: existing rows keep their current
 * detail rows and simply default to `bank_transfer`, so no instruction shown to
 * an existing order changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            // Which required detail fields apply, and whether a QR image is
            // expected. Defaults to the most common manual method so the rows
            // created by PaymentMethodSeeder before Phase 12B stay valid.
            $table->string('type', 32)
                ->default('bank_transfer')
                ->after('label');

            // Relative path of the uploaded QR merchant image. Never a public
            // URL in the database: the URL is derived from the configured disk
            // when rendered, so moving to object storage does not rewrite rows.
            $table->string('qr_image_path', 255)
                ->nullable()
                ->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['type', 'qr_image_path']);
        });
    }
};
