<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Role;
use App\Models\User;

/**
 * Authorisation for role management (Phase 10).
 *
 * Reading roles and changing them are separate permissions so an admin can be
 * allowed to inspect access without being able to grant it. The Super Admin
 * role is protected: no permission, including Super Admin's own, may edit or
 * delete it. That keeps one guaranteed way back into the panel.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(AdminPermission::RolesView);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission(AdminPermission::RolesView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(AdminPermission::RolesManage);
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission(AdminPermission::RolesManage) && ! $role->isProtected();
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission(AdminPermission::RolesManage) && ! $role->isProtected();
    }
}
