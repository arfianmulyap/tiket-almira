<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Admin account lifecycle and one-time invitations (Phase 11).
 *
 * An account now has three states: `invited` (created but no password yet),
 * `active` (may use the panel), and `inactive` (access revoked). The password
 * column becomes nullable because an invited account deliberately has none
 * until the recipient chooses one through the invitation link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Defaulted to `active` so every account that existed before this
            // migration keeps working exactly as it did.
            $table->string('status', 20)->default('active')->after('role_id')->index();

            // Set when access is revoked, so the panel can show when and (via
            // the audit trail) by whom.
            $table->timestamp('deactivated_at')->nullable()->after('status');

            // Invited accounts have no password until the invitation is
            // accepted. The `hashed` cast leaves null untouched.
            $table->string('password')->nullable()->change();
        });

        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->id();

            // One account, one pending invitation: accepting or reissuing
            // revokes the previous row instead of deleting it, so the history
            // of who was invited and when survives.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Snapshot of the address the mail went to: if the account email
            // changes later, the trail still shows where the invitation was
            // delivered.
            $table->string('email', 255);

            $table->foreignId('role_id')->nullable()->constrained()->nullOnDelete();

            // Only the SHA-256 of the token is stored. The plaintext token
            // exists just long enough to build the link in the email, so a
            // database leak cannot be replayed into an account takeover.
            $table->string('token_hash', 64)->unique();

            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // The account list asks for the pending invitation of each row.
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');

        // Restoring NOT NULL would fail on invited accounts that never chose a
        // password, so they get an unusable random one first. They stay
        // `inactive`-equivalent: without the invitation flow they cannot sign
        // in, and an admin can still reset them through the seeder.
        DB::table('users')->whereNull('password')->update([
            'password' => Hash::make(Str::random(40)),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
            $table->dropColumn(['status', 'deactivated_at']);
        });
    }
};
