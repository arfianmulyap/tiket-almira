<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Align the Phase 12 payment permission keys with the Phase 12B catalog.
 *
 * Phase 12 shipped two permissions whose work is now split into four:
 *
 *   payment-methods.view   -> payment_methods.view
 *   payment-methods.manage -> payment_methods.toggle
 *
 * plus new `payment_methods.create`, `payment_methods.update`, and
 * `payment_methods.delete` (created by RolePermissionSeeder from
 * App\Enums\AdminPermission).
 *
 * The rename is done by id, so the role grants in `permission_role` stay
 * attached: an admin who could toggle a method before this migration can still
 * toggle it afterwards. Deleting the old rows and letting the seeder recreate
 * them would silently revoke access, which is exactly the kind of change that
 * must not happen as a side effect of a deploy.
 *
 * `label`, `group_name`, and `description` are deliberately left untouched: an
 * installation may have edited them through the permission screen, and only the
 * machine key is load-bearing.
 */
return new class extends Migration
{
    /**
     * @var array<string, string> Old key => new key.
     */
    private const RENAMES = [
        'payment-methods.view' => 'payment_methods.view',
        'payment-methods.manage' => 'payment_methods.toggle',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /**
     * Rename each key in $renames (from => to), merging into an existing target
     * rather than failing on the unique index when the seeder already created
     * the new key.
     *
     * @param  array<string, string>  $renames
     */
    private function rename(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $source = DB::table('permissions')->where('name', $from)->first();

            if ($source === null) {
                continue;
            }

            $target = DB::table('permissions')->where('name', $to)->first();

            if ($target === null) {
                DB::table('permissions')
                    ->where('id', $source->id)
                    ->update(['name' => $to, 'updated_at' => now()]);

                continue;
            }

            // Both rows exist (the seeder ran first). Move the grants, then
            // retire the duplicate so no role loses access.
            $roleIds = DB::table('permission_role')
                ->where('permission_id', $source->id)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $target->id,
                ]);
            }

            DB::table('permission_role')->where('permission_id', $source->id)->delete();
            DB::table('permissions')->where('id', $source->id)->delete();
        }
    }
};
