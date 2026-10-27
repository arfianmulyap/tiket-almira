<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * An issued e-ticket.
 *
 * Nothing here is mass assignable: every attribute is derived server-side by
 * TicketIssuanceService after a payment has been approved. A ticket must never
 * be creatable from request input (PRD FR-09).
 */
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The check-in that admitted this ticket's holder, if any.
     *
     * HasOne, enforced by a unique index on check_ins.ticket_id: one token
     * yields at most one valid check-in (PRD business rule 11).
     *
     * @return HasOne<CheckIn, $this>
     */
    public function checkIn(): HasOne
    {
        return $this->hasOne(CheckIn::class);
    }

    /**
     * Whether this ticket may still be used to check in.
     */
    public function allowsCheckIn(): bool
    {
        return $this->status->allowsCheckIn();
    }

    /**
     * Whether this ticket has already been used to check in.
     */
    public function isCheckedIn(): bool
    {
        return $this->status === TicketStatus::CheckedIn;
    }

    /**
     * Every recorded attempt to email this ticket, newest first (Phase 13).
     *
     * A retry appends a row rather than replacing one, so the order detail page
     * can show that the first attempt failed and the resend worked. Ordered in
     * the relation so no caller has to remember to sort.
     *
     * @return MorphMany<EmailDelivery, $this>
     */
    public function emailDeliveries(): MorphMany
    {
        return $this->morphMany(EmailDelivery::class, 'emailable')->latest('id');
    }

    /**
     * Outcome of the most recent delivery attempt, or null if never attempted.
     */
    public function latestDelivery(): ?EmailDelivery
    {
        return $this->emailDeliveries->first();
    }
}
