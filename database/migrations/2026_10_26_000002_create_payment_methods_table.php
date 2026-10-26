<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent payment-method configuration (Phase 12).
 *
 * Until Phase 12 the catalog lived only in `config/payments.php`, because PRD
 * section 15 question 16 (global versus per-event methods) was unanswered.
 * Phase 12 requires an authorised admin to toggle a method and a Super Admin
 * to maintain the account, VA, and QRIS details without a deployment, which
 * needs a table. The empty-table fallback in PaymentMethodRegistry keeps an
 * installation that has not run PaymentMethodSeeder working exactly as before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();

            // Stable key referenced by payments.method and by order snapshots.
            // Never edited after creation, which is why no form field exists
            // for it: renaming it would orphan existing payment records.
            $table->string('key', 64)->unique();

            // Shown in the participant's method picker.
            $table->string('label', 120);

            // Whether the method is offered to participants at all. Retiring a
            // method is a toggle, never a delete, so history stays resolvable.
            $table->boolean('is_active')->default(false);

            // Placeholder data must never reach participants in production.
            // PaymentMethodRegistry hides flagged rows there; only a Super
            // Admin may clear this flag, and only after supplying the real
            // details the business confirmed.
            $table->boolean('is_dummy')->default(true);

            // Ordered rows: [['label' => 'Bank', 'value' => 'BSI'], ...].
            // A list, not an object, because MySQL's JSON type does not
            // preserve object key order and the order is meaningful.
            $table->json('account')->nullable();

            // Ordered transfer steps, one string per step.
            $table->json('steps')->nullable();

            // Display order in the picker and the settings table.
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Last admin who changed the configuration. Nullable so deleting
            // an account does not erase the method; the audit trail keeps the
            // actor name independently.
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // The settings screen lists by order; the participant side filters
            // on is_active first.
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
