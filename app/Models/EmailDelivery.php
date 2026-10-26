<?php

namespace App\Models;

use App\Enums\EmailDeliveryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One recorded attempt to deliver a transactional email (Phase 13).
 *
 * Written by EmailDeliveryLogger and never edited afterwards, so the table is
 * an append-only trail like audit_logs. That is what makes a retry readable:
 * the failed attempt stays next to the attempt that finally worked.
 *
 * Nothing is mass assignable. The recipient, status, and actor are all decided
 * by the service that talks to the mail transport, never by request input.
 */
class EmailDelivery extends Model
{
    /**
     * Attempts are recorded once, so there is no `updated_at` column.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailDeliveryStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * The ticket or admin invitation this attempt belongs to.
     *
     * @return MorphTo<Model, $this>
     */
    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The admin who triggered the attempt, if any.
     *
     * @return BelongsTo<User, $this>
     */
    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * Name shown in the panel. Automatic sends during an approval still have an
     * actor; scheduled or already-deleted accounts do not.
     */
    public function actorName(): string
    {
        return filled($this->sentBy?->name) ? (string) $this->sentBy->name : 'Sistem';
    }
}
