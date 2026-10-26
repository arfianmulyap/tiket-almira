<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Loads the permission catalog and guarantees the Super Admin role exists.
 *
 * Safe to run repeatedly: existing rows keep the labels an admin may have
 * edited, which is why firstOrCreate is used rather than updateOrCreate.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminPermission::cases() as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission->value],
                [
                    'label' => $permission->label(),
                    'group_name' => $permission->group(),
                    'description' => $permission->description(),
                ],
            );
        }

        $this->ensureSuperAdminRole();

        $this->command?->info(
            'Katalog permission siap: '.count(AdminPermission::cases()).' permission.'
        );
    }

    /**
     * The migration creates the role, so this only repairs an installation
     * where it was removed. It also adopts an existing role named "Super
     * Admin" instead of failing on the unique name.
     */
    private function ensureSuperAdminRole(): void
    {
        if (Role::query()->where('is_super_admin', true)->exists()) {
            return;
        }

        $role = Role::query()->where('name', 'Super Admin')->first() ?? new Role;

        $role->name = 'Super Admin';
        $role->description ??= 'Akses penuh ke seluruh panel admin. Permanen dan tidak dapat diubah.';
        $role->is_super_admin = true;

        $role->save();
    }
}
