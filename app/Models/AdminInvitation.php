<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A one-time invitation for an admin account (Phase 11).
 *
 * Rows are append-only in spirit: accepting sets `accepted_at`, reissuing or
 * deactivating sets `revoked_at`. Nothing is deleted, so it is always possible
 * to see that an invitation existed, when it expired, and whether it was used.
 */
class AdminInvitation extends Model
{
    /**
     * The plaintext token, set only on the instance that created it so the
     * email can be built. It is never an attribute and never persisted; the
     * database only holds its SHA-256 hash.
     */
    public ?string $plainToken = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Still usable: not accepted, not revoked, and not past its 24-hour life.
     */
    public function isUsable(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    /**
     * Every recorded delivery attempt for this invitation, newest first
     * (Phase 13).
     *
     * Ordered in the relation so every screen that reads it — the account list
     * and the audit view — sees the most recent attempt first without repeating
     * the ordering.
     *
     * @return MorphMany<EmailDelivery, $this>
     */
    public function emailDeliveries(): MorphMany
    {
        return $this->morphMany(EmailDelivery::class, 'emailable')->latest('id');
    }

    /**
     * Outcome of the most recent attempt, if the invitation has been sent.
     */
    public function latestDelivery(): ?EmailDelivery
    {
        return $this->emailDeliveries->first();
    }
}
