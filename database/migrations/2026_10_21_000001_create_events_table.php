<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the events table.
     *
     * Field choices follow PRD section 12. The required/optional split is an
     * assumption recorded against PRD open question 8 and approved by the
     * business owner: name, starts_at, location, ticket_price, and quota are
     * mandatory, description is optional.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);

            // Slug drives the public event URL in Phase 04. It is generated
            // once on create and kept stable afterwards so that links and
            // QR codes already shared with participants keep working.
            $table->string('slug', 180)->unique();

            $table->text('description')->nullable();

            // Relative path on the `public` disk. Nullable because a poster is
            // only mandatory before publishing (PRD FR-03).
            $table->string('poster_path')->nullable();

            // Rupiah stored as a whole-number amount. An integer column avoids
            // the rounding errors a float would introduce on money, and the
            // order snapshot in Phase 04 copies this value.
            $table->unsignedBigInteger('ticket_price');

            $table->unsignedInteger('quota');

            $table->enum('status', ['draft', 'published', 'closed'])
                ->default('draft')
                ->index();

            $table->dateTime('starts_at');
            $table->string('location', 200);

            $table->timestamps();

            // Public listings filter on status and order by start date.
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
