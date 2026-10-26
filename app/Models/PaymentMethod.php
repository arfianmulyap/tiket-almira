<?php

namespace App\Models;

use App\Support\PaymentMethod as PaymentMethodValue;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A configurable manual payment method (Phase 12).
 *
 * The key is the stable identifier referenced by `payments.method` and by
 * order snapshots. It is never editable through the panel; only the label,
 * instructions, active flag, and placeholder flag can change.
 */
#[Fillable(['key', 'label', 'is_active', 'is_dummy', 'account', 'steps', 'sort_order'])]
class PaymentMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_dummy' => 'boolean',
            'account' => 'array',
            'steps' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The admin who last changed this method's configuration.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Present the row through the typed value object the rest of the
     * application reads.
     */
    public function toValue(): PaymentMethodValue
    {
        return PaymentMethodValue::fromRecord([
            'key' => $this->key,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'is_dummy' => $this->is_dummy,
            'account' => $this->account ?? [],
            'steps' => $this->steps ?? [],
        ]);
    }

    /**
     * Snapshot form written onto a new order.
     *
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return $this->toValue()->toSnapshot();
    }
}
