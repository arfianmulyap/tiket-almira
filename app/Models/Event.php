<?php

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name',
    'slug',
    'description',
    'ticket_price',
    'quota',
    'status',
    'starts_at',
    'location',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * `poster_path` is deliberately left out of the fillable list: it may only
     * be written by the upload service after the file has been validated and
     * stored, never straight from request input.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ticket_price' => 'integer',
            'quota' => 'integer',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Tickets issued for this event, reached through its orders.
     *
     * There is no direct event_id on tickets: a ticket belongs to an order and
     * the order owns the event reference, so the join is the only way to count
     * issued tickets per event without denormalising. Used by the Phase 09
     * dashboard to aggregate issuance and check-ins per event.
     *
     * @return HasManyThrough<Ticket, Order, $this>
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, Order::class);
    }

    /**
     * Limit the query to events participants are allowed to order.
     *
     * Phase 04 builds the public listing on top of this scope so the
     * "unpublished events cannot be ordered" rule lives in one place.
     *
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    /**
     * Whether this event currently accepts orders.
     *
     * Quota availability is intentionally not considered here; that check
     * belongs to the ordering flow in Phase 04, which has to evaluate it
     * inside a transaction to stay safe against concurrent requests.
     */
    public function isOrderable(): bool
    {
        return $this->status->allowsOrdering();
    }

    /**
     * Whether the event is still a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === EventStatus::Draft;
    }

    /**
     * Number of quota slots already taken by orders.
     *
     * Delegates the definition of "holds a slot" to Order::scopeHoldingQuota
     * so the admin quota rule, the public availability display, and the
     * ordering transaction all count the same thing.
     */
    public function reservedQuota(): int
    {
        return $this->orders()->holdingQuota()->count();
    }

    /**
     * Quota still available for new orders.
     */
    public function remainingQuota(): int
    {
        return max(0, $this->quota - $this->reservedQuota());
    }

    /**
     * Whether at least one slot is still free.
     *
     * Safe for display only. The ordering flow must re-check this inside a
     * locked transaction (see OrderService) because the answer can change
     * between rendering the page and submitting the form.
     */
    public function hasAvailableQuota(): bool
    {
        return $this->remainingQuota() > 0;
    }

    /**
     * Whether a participant can order right now: published and not sold out.
     */
    public function isAvailableForOrdering(): bool
    {
        return $this->isOrderable() && $this->hasAvailableQuota();
    }

    /**
     * Public URL of the poster, or null when no poster has been uploaded.
     *
     * The disk is read from config rather than hardcoded so the URL follows
     * the poster to wherever it is actually stored: the local public disk in
     * development, or an object storage bucket in an environment with no
     * persistent filesystem. EventPosterService writes to the same disk.
     */
    public function posterUrl(): ?string
    {
        if (blank($this->poster_path)) {
            return null;
        }

        return Storage::disk(config('filesystems.poster_disk', 'public'))
            ->url($this->poster_path);
    }
}
