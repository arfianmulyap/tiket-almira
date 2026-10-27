<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles, permissions, and the single role held by each admin (Phase 10).
 *
 * The Super Admin row is inserted here rather than in a seeder because the
 * backfill at the end of up() needs a role to point the existing admins at.
 * Without it, deploying this migration would strip every current admin of the
 * panel until a seeder was run manually.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description', 255)->nullable();

            // Flags the permanent, protected Super Admin role. Stored as a
            // flag rather than matched by name so renaming the role (or a
            // second role reusing the name) can never bypass the protection.
            $table->boolean('is_super_admin')->default(false);

            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            // Machine key checked by the backend, e.g. "events.create". The
            // allowed values live in App\Enums\AdminPermission: a permission
            // that no action consults would give false confidence.
            $table->string('name', 100)->unique();

            // Display metadata, grouped for the role permission matrix.
            $table->string('label', 120);
            $table->string('group_name', 60);
            $table->string('description', 255)->nullable();

            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();

            // Composite primary key: a permission can only be granted once.
            $table->primary(['role_id', 'permission_id']);
        });

        // One role per admin. A plain foreign key rather than a pivot table
        // so the "satu role untuk setiap admin" rule is enforced by the schema
        // instead of by application code alone.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                ->nullable()
                ->after('is_admin')
                ->constrained()
                // Deleting a role must never delete its admins. It leaves them
                // without a role, which EnsureUserIsAdmin refuses.
                ->nullOnDelete();
        });

        $superAdminId = DB::table('roles')->insertGetId([
            'name' => 'Super Admin',
            'description' => 'Akses penuh ke seluruh panel admin. Permanen dan tidak dapat diubah.',
            'is_super_admin' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Every account that could already reach the panel keeps the same
        // access it had, now through the Super Admin role.
        DB::table('users')
            ->where('is_admin', true)
            ->update(['role_id' => $superAdminId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
