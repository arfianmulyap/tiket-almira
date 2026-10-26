<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Permission;
use App\Models\User;

/**
 * Authorisation for the permission catalog (Phase 10).
 *
 * Managing the catalog is a single permission: the screen only exists to
 * restore, relabel, or remove definitions, so a read-only viewer would have
 * nothing to do there.
 */
class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(AdminPermission::PermissionsManage);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->hasPermission(AdminPermission::PermissionsManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(AdminPermission::PermissionsManage);
    }

    public function update(User $user, Permission $permission): bool
    {
        return $user->hasPermission(AdminPermission::PermissionsManage);
    }

    public function delete(User $user, Permission $permission): bool
    {
        return $user->hasPermission(AdminPermission::PermissionsManage);
    }
}
