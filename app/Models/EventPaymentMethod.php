<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An explicit per-event activation override for one payment method (Phase 12B).
 *
 * The absence of a row means "follow the global `payment_methods.is_active`
 * flag". Recording an override only when an event disagrees keeps the global
 * catalog authoritative, and it is what makes "disabling a method for one event
 * must not disable it for other events" true by construction: one row, one
 * event, no shared state to get wrong.
 *
 * This is deliberately not a pivot table with a flag on the payment method
 * itself. The global row answers "does this method exist and where does it send
 * money"; this row answers only "is it offered for this event".
 */
#[Fillable(['event_id', 'payment_method_id', 'is_active', 'updated_by'])]
class EventPaymentMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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
     * The global method this override applies to.
     *
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * The admin who last changed the override, when one was recorded.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
