<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery trail for transactional email (Phase 13).
 *
 * PRD FR-09 requires that a delivery failure is recorded and can be retried,
 * and Phase 13 asks for "status pengiriman, retry, dan pencatatan hasil".
 * Neither the log file nor the audit trail can answer "was this participant's
 * e-ticket actually emailed?" per order, so every attempt gets its own row.
 *
 * One table serves both mailables in the app — the e-ticket and the admin
 * invitation — because the question, the fields, and the retry action are
 * identical. Rows are append-only: a retry adds a new row instead of
 * overwriting the failed one, so the history of a flaky mail server stays
 * readable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_deliveries', function (Blueprint $table) {
            $table->id();

            // Morph target: Ticket or AdminInvitation. Indexed by morphs(),
            // and reads order by id, which is also the attempt order.
            $table->morphs('emailable');

            // Copied at send time, because it is what the transport was
            // actually told. A later change to the participant's email must not
            // rewrite the history of what was sent.
            $table->string('recipient_email');

            // SENT / FAILED / SKIPPED — see App\Enums\EmailDeliveryStatus.
            $table->string('status', 16);

            // Transport error text, so an operator can act from the panel
            // without opening storage/logs/laravel.log. Null unless FAILED.
            $table->text('error_message')->nullable();

            // Null when there was no authenticated actor, or when the admin
            // account has since been deleted. The attempt itself stays.
            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Append-only row, like audit_logs: no updated_at to maintain.
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_deliveries');
    }
};
