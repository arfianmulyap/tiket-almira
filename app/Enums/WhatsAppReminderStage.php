<?php

namespace App\Enums;

/**
 * The two payment reminders scheduled in Phase 14.
 *
 * The stored value is also `whatsapp_deliveries.kind` and half of the unique
 * key that makes a stage queue at most once per order. Changing a case value
 * therefore needs a data migration, not just a code change.
 */
enum WhatsAppReminderStage: string
{
    case Minute30 = 'payment_reminder_30';
    case Minute50 = 'payment_reminder_50';

    /**
     * Minutes after order creation at which this reminder becomes due.
     */
    public function minute(): int
    {
        return match ($this) {
            self::Minute30 => 30,
            self::Minute50 => 50,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Minute30 => 'Pengingat menit ke-30',
            self::Minute50 => 'Pengingat menit ke-50',
        };
    }

    /**
     * @return array<int, self>
     */
    public static function ordered(): array
    {
        return [self::Minute30, self::Minute50];
    }
}
