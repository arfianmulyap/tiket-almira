<?php

namespace App\Models;

use App\Enums\WhatsAppDeliveryStatus;
use App\Enums\WhatsAppReminderStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled WhatsApp reminder for one order (Phase 14).
 *
 * The recipient, stage, attempts, and status are all decided server-side by
 * WhatsAppReminderService. Nothing here comes from request input, and the
 * unique key on (order_id, kind) makes a duplicate stage impossible.
 */
#[Fillable([
    'order_id',
    'kind',
    'recipient_phone',
    'status',
    'attempts',
])]
class WhatsAppDelivery extends Model
{
    /**
     * Laravel's pluraliser would turn "WhatsAppDelivery" into
     * "whats_app_deliveries". Pinned so the model and migration cannot drift.
     */
    protected $table = 'whatsapp_deliveries';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WhatsAppReminderStage::class,
            'status' => WhatsAppDeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
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
     * Human readable stage name, falling back to the stored value if a future
     * stage is ever added before this enum is updated.
     */
    public function stageLabel(): string
    {
        return $this->kind?->label() ?? (string) $this->getRawOriginal('kind');
    }
}
