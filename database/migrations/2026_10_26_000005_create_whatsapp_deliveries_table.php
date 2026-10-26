<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery trail for scheduled WhatsApp payment reminders (Phase 14).
 *
 * Phase 14 asks for "retry terbatas, status pengiriman, dan pencegahan
 * duplikasi pesan". Those three requirements are answered by one row per
 * reminder stage per order, with a unique key on (order_id, kind):
 *
 * - the unique key is the duplicate guard, even if two scheduler processes
 *   run at the same time;
 * - attempts is the bounded retry counter;
 * - status/error_message/driver/sent_at are the delivery status an operator
 *   reads on the order screen.
 *
 * The row is updated rather than appended because each stage has one row and
 * one lifecycle: PENDING, FAILED while retries remain, then SENT or SKIPPED.
 * Email deliveries stay append-only, but a reminder that can be retried would
 * otherwise grow a new row on every attempt and lose the "one message per
 * stage" guarantee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_deliveries', function (Blueprint $table) {
            $table->id();

            // Orders are never deleted through the application. Cascading
            // keeps the reminder trail from blocking maintenance if an order
            // is ever removed directly in the database.
            $table->foreignId('order_id')
                ->constrained()
                ->cascadeOnDelete();

            // payment_reminder_30 / payment_reminder_50;
            // see App\Enums\WhatsAppReminderStage.
            $table->string('kind', 32);

            // Copied at queue time in the gateway's expected 62... form, so a
            // later edit to the order cannot rewrite what was attempted.
            $table->string('recipient_phone', 32);

            // Name reported by the gateway driver (currently `log`), so the
            // panel can show that a local simulation did not reach a provider.
            $table->string('driver', 32)->nullable();

            // PENDING / SENT / FAILED / SKIPPED;
            // see App\Enums\WhatsAppDeliveryStatus.
            $table->string('status', 16);

            $table->unsignedTinyInteger('attempts')->default(0);

            // Gateway error, so an operator can act without opening the log.
            // Also holds the reason a reminder was deliberately skipped.
            $table->text('error_message')->nullable();

            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            // The duplicate guard: one row per order and reminder stage.
            $table->unique(['order_id', 'kind']);

            // The scheduler scans unfinished rows on every run.
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_deliveries');
    }
};
