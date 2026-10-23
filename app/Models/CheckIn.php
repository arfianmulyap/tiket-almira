<?php

namespace App\Models;

use Database\Factories\CheckInFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record of one participant being admitted to an event (PRD FR-10).
 *
 * Nothing is mass assignable. The ticket is resolved server-side from the
 * scanned token, the operator comes from the authenticated session, and the
 * timestamp is server time. A scanning device is an untrusted client: if it
 * could supply any of these it could admit an arbitrary ticket, credit the
 * check-in to someone else, or backdate it.
 */
class CheckIn extends Model
{
    /** @use HasFactory<CheckInFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * The officer who admitted the participant.
     *
     * @return BelongsTo<User, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Name of the officer, falling back to the stored copy.
     *
     * The denormalised column is what keeps the gate log readable after an
     * admin account is deleted.
     */
    public function operatorName(): string
    {
        return $this->operator?->name
            ?? $this->operator_name
            ?? 'petugas yang sudah dihapus';
    }
}
