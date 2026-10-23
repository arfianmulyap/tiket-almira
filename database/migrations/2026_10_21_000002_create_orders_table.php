<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the orders table (PRD section 12).
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Human-readable reference shown to the participant and used by
            // admins when matching a bank transfer. Unique, but NOT a secret:
            // participants may quote it over WhatsApp, so it must never be
            // enough on its own to open an order.
            $table->string('order_reference', 32)->unique();

            // Secret that authorises access to the order status page. Kept
            // separate from order_reference precisely because the reference
            // is shareable. PRD open question 6 is still unresolved; this is
            // the minimum safe mechanism and is labelled as an assumption.
            $table->string('access_token', 64)->unique();

            // Restricted rather than cascading: deleting an event that has
            // orders would destroy financial records. Phase 03 already
            // blocks event deletion in the policy; this enforces it in the
            // database too.
            $table->foreignId('event_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('participant_name', 120);
            $table->string('participant_whatsapp', 20);
            $table->string('participant_email', 160);

            // Copy of the event price at the moment of ordering. PRD business
            // rule 12: later price changes must not alter existing orders.
            $table->unsignedBigInteger('ticket_price_snapshot');

            $table->enum('status', OrderStatus::values())
                ->default(OrderStatus::PendingPayment->value);

            // created_at + 60 minutes, computed server-side only.
            $table->dateTime('expires_at');

            $table->timestamps();

            // The quota count filters on event + status, and the expiry sweep
            // in Phase 06 filters on status + expires_at.
            $table->index(['event_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
