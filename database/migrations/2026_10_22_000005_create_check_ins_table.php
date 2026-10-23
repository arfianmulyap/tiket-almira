<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records of participants admitted to an event (PRD FR-10).
 *
 * A separate table rather than a pair of columns on `tickets` because the
 * check-in is an event with its own actor and timestamp, and keeping it apart
 * leaves room for the reset/cancel policy that PRD section 15 question 14
 * still leaves open: reversing a check-in then becomes deleting or voiding a
 * row here instead of nulling fields on the ticket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table) {
            $table->id();

            // UNIQUE is the real guarantee behind "satu token tiket hanya
            // boleh dipakai untuk satu check-in sah" (PRD business rule 11).
            // CheckInService also takes a row lock on the ticket and re-reads
            // its status, but if a race ever slipped past that lock the
            // database would still refuse the second insert.
            $table->foreignId('ticket_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            // The officer who admitted the participant. Nullable only so the
            // trail survives the account being removed later; every check-in
            // written by this application has an authenticated actor.
            $table->foreignId('checked_in_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Denormalised copy, same reasoning as audit_logs.user_name: the
            // record must stay readable after an account is deleted.
            $table->string('operator_name')->nullable();

            // Server time, never supplied by the scanning device.
            $table->dateTime('checked_in_at');

            $table->timestamps();

            // Supports "who came in, most recent first" for the gate screen
            // and the Phase 09 dashboard.
            $table->index('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
