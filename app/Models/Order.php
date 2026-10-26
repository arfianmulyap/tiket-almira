<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Only the participant-supplied fields are fillable.
 *
 * order_reference, access_token, ticket_price_snapshot, status, and
 * expires_at are all set by OrderService from server-side values. PRD
 * section 7 is explicit that price, status, and deadlines must never be
 * taken from request input.
 */
#[Fillable([
    'participant_name',
    'participant_whatsapp',
    'participant_email',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'ticket_price_snapshot' => 'integer',
            // Frozen at creation: the methods and instructions this
            // participant was shown (Phase 12). Later settings changes must
            // not rewrite them.
            'payment_instructions_snapshot' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The issued ticket, if the payment has been approved.
     *
     * HasOne, enforced by a unique index on tickets.order_id: one order
     * yields at most one ticket (PRD business rule 10).
     *
     * @return HasOne<Ticket, $this>
     */
    public function ticket(): HasOne
    {
        return $this->hasOne(Ticket::class);
    }

    /**
     * The submitted manual payment, if a proof has been uploaded.
     *
     * HasOne, enforced by a unique index on payments.order_id: the MVP accepts
     * one proof per order. Re-upload after a rejection is an open business
     * question (PRD section 15 question 3), so no second payment is possible
     * until that is answered.
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * The admin who approved or rejected the payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return MorphMany<AuditLog, $this>
     */
    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * Scheduled WhatsApp payment reminders for this order (Phase 14).
     *
     * At most one row per reminder stage, enforced by a unique index on
     * (order_id, kind). The relation lets the admin screen show delivery
     * status without a query per stage.
     *
     * @return HasMany<WhatsAppDelivery, $this>
     */
    public function whatsappDeliveries(): HasMany
    {
        return $this->hasMany(WhatsAppDelivery::class);
    }

    /**
     * Orders that currently hold a quota slot.
     *
     * A PENDING_PAYMENT order holds its slot only until expires_at; past
     * that point the slot is free even if the expiry sweep (Phase 06) has
     * not yet flipped the status. Counting it this way means quota is never
     * overstated just because the scheduler is late, and it keeps the count
     * correct when the sweep runs more than once.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeHoldingQuota(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query
                ->whereIn('status', OrderStatus::holdingQuotaUnconditionally())
                ->orWhere(function (Builder $query) {
                    $query
                        ->where('status', OrderStatus::PendingPayment)
                        ->where('expires_at', '>', now());
                });
        });
    }

    /**
     * Unpaid orders whose deadline has passed but whose status has not been
     * swept yet.
     *
     * This is the exact complement of the time condition in
     * scopeHoldingQuota(): that scope keeps a PENDING_PAYMENT slot while
     * `expires_at > now()`, so an order is overdue once `expires_at <= now()`.
     * Keeping the two boundaries complementary means no order can ever be
     * counted as holding a slot and be eligible for expiry at the same time.
     *
     * Only PENDING_PAYMENT is matched, so a proof submitted in time
     * (PENDING_VERIFICATION) or an approved payment (PAID) can never be
     * expired by the sweep (PRD FR-08).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query
            ->where('status', OrderStatus::PendingPayment)
            ->where('expires_at', '<=', now());
    }

    /**
     * Orders waiting for an admin payment decision.
     *
     * Deliberately has no deadline condition: once a proof has arrived in
     * time the order keeps its slot and stays in the queue past minute 60
     * (PRD FR-08).
     *
     * @param  Builder<Order>  $query
     */
    public function scopeAwaitingVerification(Builder $query): void
    {
        $query->where('status', OrderStatus::PendingVerification);
    }

    /**
     * Filter orders by a free-text admin search term (PRD FR-11).
     *
     * Matches the order reference and the three participant fields. The term is
     * bound as a parameter, and the LIKE wildcards `%` and `_` are escaped, so
     * a term such as "%" matches a literal percent sign instead of silently
     * returning every row.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
        $like = '%'.$escaped.'%';

        $query->where(function (Builder $query) use ($like) {
            $query
                ->where('order_reference', 'like', $like)
                ->orWhere('participant_name', 'like', $like)
                ->orWhere('participant_email', 'like', $like)
                ->orWhere('participant_whatsapp', 'like', $like);
        });
    }

    /**
     * Whether an admin may still approve or reject this order.
     *
     * Only an order whose proof is awaiting review is decidable. This is the
     * single definition used by the policy, the service, and the views, so a
     * decision cannot be taken twice or on an expired order.
     */
    public function awaitsDecision(): bool
    {
        return $this->status === OrderStatus::PendingVerification;
    }

    /**
     * Whether the payment was rejected by an admin.
     */
    public function isRejected(): bool
    {
        return $this->status === OrderStatus::Rejected;
    }

    /**
     * Whether the payment deadline has passed.
     */
    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether the participant may still upload a payment proof.
     *
     * Both conditions matter: the status must still be awaiting a proof and
     * the deadline must not have passed. Checking the clock as well as the
     * status means a late upload is refused even when the expiry sweep has
     * not run yet (PRD FR-06).
     */
    public function acceptsPaymentProof(): bool
    {
        return $this->status->awaitsProof() && ! $this->hasExpired();
    }

    /**
     * Whether a submitted proof is waiting for an admin decision.
     */
    public function isAwaitingVerification(): bool
    {
        return $this->status === OrderStatus::PendingVerification;
    }

    /**
     * Whether the payment has been approved by an admin.
     */
    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * Seconds left before the payment deadline, floored at zero.
     */
    public function secondsUntilExpiry(): int
    {
        // Carbon returns a float here; cast before comparing so the return
        // type is satisfied without an implicit conversion.
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
