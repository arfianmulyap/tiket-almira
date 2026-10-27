<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-event activation of a payment method (Phase 12B).
 *
 * Payment *details* are global and shared by every event. Whether a method is
 * *offered* is a per-event decision: the same event organizer may accept a bank
 * transfer for one event and only QRIS for another.
 *
 * A row here is an explicit override. No row means "follow the global
 * `payment_methods.is_active` flag", which is what keeps every installation
 * that never touches this screen behaving exactly as it did in Phase 12. The
 * override is a third state on purpose: recording an explicit `true` for the
 * events that disagree is what makes "disabling a method for one event must not
 * disable it for other events" true without touching any other row.
 *
 * The unique index is the correctness guarantee: two admins toggling the same
 * method for the same event at the same time cannot create two conflicting
 * overrides.
 *
 * `payment_method_id` cascades on delete so a method that is genuinely safe to
 * delete (no payments and no order snapshots reference it — checked in
 * PaymentMethodController) does not leave stale activation rows behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_payment_methods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('event_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('payment_method_id')
                ->constrained()
                ->cascadeOnDelete();

            // Explicit override for this event only. Never written back to the
            // global row: that is the whole point of the table.
            $table->boolean('is_active')->default(true);

            // Last admin who changed the override for this event. Nullable so
            // deleting an account does not erase the override; the audit trail
            // keeps the actor name independently.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // One override per method per event.
            $table->unique(['event_id', 'payment_method_id']);

            // The participant-facing lookup is always "overrides for one
            // event", which this index answers directly.
            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_payment_methods');
    }
};
