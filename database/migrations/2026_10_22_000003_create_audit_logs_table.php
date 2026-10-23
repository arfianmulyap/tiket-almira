<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only trail of operationally important actions (PRD FR-12).
 *
 * Deliberately has no `updated_at`: rows are written once and never edited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Morph target so payment decisions, ticket issuance, and the
            // Phase 08 check-ins all land in one trail.
            $table->morphs('auditable');

            $table->string('action', 64);

            // Nullable: the expiry sweep runs from the scheduler with no
            // authenticated user (PRD FR-12 "jika tersedia").
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Denormalised copy: the trail must stay readable even if the
            // admin account is later removed.
            $table->string('user_name')->nullable();

            $table->json('metadata')->nullable();

            $table->dateTime('created_at');

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
