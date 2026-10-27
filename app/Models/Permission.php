<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One permission a role may hold (Phase 10).
 *
 * `name` is the machine key checked by the backend and is therefore not
 * mass assignable: it is written explicitly from App\Enums\AdminPermission, so
 * a permission can never be created with a key no action checks. Only the
 * display metadata is editable.
 */
#[Fillable(['label', 'group_name', 'description'])]
class Permission extends Model
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
