<?php

namespace App\Models;

use App\Enums\AdminPermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named set of permissions, assigned to exactly one admin per user (Phase 10).
 *
 * `is_super_admin` is intentionally not fillable: only the migration and the
 * seeders may flag the protected role, never request input.
 */
#[Fillable(['name', 'description'])]
class Role extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The Super Admin role is permanent and always holds every permission,
     * including permissions added after it was created.
     */
    public function isProtected(): bool
    {
        return $this->is_super_admin === true;
    }

    /**
     * Whether this role grants the given permission.
     *
     * Read from the database on every request rather than cached, so a change
     * to a role's permissions applies to the next request (Phase 10
     * requirement). Within one request Eloquent keeps the loaded relation,
     * which is the documented behaviour: changes never apply mid-request.
     */
    public function hasPermission(AdminPermission|string $permission): bool
    {
        if ($this->isProtected()) {
            return true;
        }

        $name = $permission instanceof AdminPermission ? $permission->value : $permission;

        return $this->permissions->contains('name', $name);
    }

    /**
     * Permission keys currently granted, sorted so audit metadata is stable
     * and comparable between two saves.
     *
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return $this->permissions()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
