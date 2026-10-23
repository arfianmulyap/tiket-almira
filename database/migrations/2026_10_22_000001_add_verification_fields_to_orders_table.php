<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records who decided a payment, when, and why it was rejected.
 *
 * PRD FR-07 requires the decision, its time, the acting admin, and the
 * rejection reason to be stored. These live on the order rather than in a
 * separate payments table because the MVP has exactly one payment per order
 * (see the OrderStatus docblock); Phase 05 will add the proof columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // nullOnDelete, not cascade: removing an admin account must never
            // delete the financial record of their decision. The audit trail
            // keeps the name separately.
            $table->foreignId('reviewed_by')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('reviewed_at')->nullable()->after('reviewed_by');

            $table->text('rejection_reason')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'rejection_reason']);
        });
    }
};
