<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One manual payment submission per order (PRD section 12, FR-06).
 *
 * This table records what the participant claims to have transferred: the
 * method they picked, the amount they were asked for, the stored proof file,
 * and the server time the proof arrived. It is deliberately NOT a record of
 * the admin's decision — reviewed_by, reviewed_at, and rejection_reason
 * already live on `orders` (Phase 07) and are not duplicated here, because two
 * copies of one decision can drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Unique: today exactly one proof may be submitted per order.
            // Re-uploading after a rejection is an unanswered business
            // question (PRD section 15 question 3), and "unanswered" is not
            // "allowed", so the database enforces the current rule instead of
            // leaving it to application code alone. This also makes a
            // double-submit race impossible rather than merely unlikely.
            //
            // If re-upload is later approved, this index is dropped by a
            // follow-up migration and `Order::payment()` becomes a
            // latestOfMany relation.
            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            // The configuration key of the chosen method, not an enum: the
            // final list of active methods is unconfirmed (PRD section 15
            // question 1), so adding or retiring a method must not require a
            // schema migration.
            $table->string('method', 64);

            // Snapshot of the label the participant actually saw, for the
            // same reason the order snapshots its price: renaming a method in
            // configuration must not rewrite history an admin is reviewing.
            $table->string('method_label', 120);

            // Snapshot of the amount due at submission time, copied from the
            // order's own price snapshot.
            $table->unsignedBigInteger('amount');

            // Relative path on the private disk. Never a URL: the file is
            // only ever streamed by an authorised controller (PRD FR-06).
            $table->string('proof_path', 255);

            // Kept so an admin reviewing the file, and the download response,
            // do not have to trust a client-supplied content type.
            $table->string('proof_mime_type', 128);

            // Server time, not client time: this is the value compared
            // against the order deadline, so it must not be influenceable by
            // the browser (PRD FR-06, FR-08).
            $table->dateTime('submitted_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
