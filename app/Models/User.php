<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AdminAccountStatus;
use App\Enums\AdminPermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Mirrors the column default so a freshly instantiated account is already
     * `active` in memory, not only after a reload (Phase 11).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'status' => AdminAccountStatus::class,
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * The single role held by this admin (Phase 10).
     *
     * One role per admin, so this is a belongsTo rather than a pivot. The
     * relation is read fresh on every request, which is what makes permission
     * changes apply to the next request without a re-login.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Administrative invitations sent to this account (Phase 11).
     *
     * Only the newest unaccepted, unrevoked row is relevant at any time; older
     * rows are kept so the trail shows that a link was reissued.
     *
     * @return HasMany<AdminInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(AdminInvitation::class);
    }

    /**
     * Whether the account may sign in and use the panel.
     *
     * Invited accounts have no password yet and deactivated accounts were cut
     * off on purpose; both are refused by the login flow and by the admin
     * middleware.
     */
    public function isActive(): bool
    {
        return $this->status === AdminAccountStatus::Active;
    }

    /**
     * Whether the account is still waiting for its invitation to be accepted.
     */
    public function isInvited(): bool
    {
        return $this->status === AdminAccountStatus::Invited;
    }

    /**
     * Determine whether the user may access the admin panel.
     *
     * `is_admin` is intentionally not mass assignable so it can only be
     * changed through explicit, server-side code paths.
     */
    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    /**
     * Whether this admin holds the permanent Super Admin role.
     */
    public function isSuperAdmin(): bool
    {
        return $this->isAdmin() && $this->role?->isProtected() === true;
    }

    /**
     * Whether the user may perform the action guarded by $permission.
     *
     * A non-admin account has no admin permissions regardless of its role, and
     * an admin without a role has none either: access comes from the role, not
     * from the account flag alone.
     */
    public function hasPermission(AdminPermission|string $permission): bool
    {
        if (! $this->isAdmin() || $this->role_id === null) {
            return false;
        }

        return $this->role?->hasPermission($permission) ?? false;
    }

    /**
     * Assign the user's one and only role.
     *
     * Written through an explicit method rather than mass assignment so a
     * role can never be set from request input without an authorisation check
     * at the call site.
     */
    public function assignRole(?Role $role): void
    {
        $this->role_id = $role?->getKey();
        $this->save();

        // Drop the cached relation so a check later in the same request sees
        // the role that was just assigned.
        $this->unsetRelation('role');
    }
}
