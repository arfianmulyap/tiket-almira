<?php

use App\Enums\TicketStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One ticket per paid order (PRD FR-09).
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            // UNIQUE is the real guarantee behind "satu pesanan menghasilkan
            // paling banyak satu tiket" (PRD business rule 10). Application
            // code is idempotent too, but a race that slipped past the lock
            // would still be refused here by the database.
            $table->foreignId('order_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();

            // The QR payload. Random and unguessable so possession of the
            // token is what proves the ticket, and it deliberately carries no
            // personal data (PRD FR-09, section 11).
            $table->string('qr_token', 64)->unique();

            $table->enum('status', TicketStatus::values())
                ->default(TicketStatus::Valid->value);

            $table->dateTime('issued_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
