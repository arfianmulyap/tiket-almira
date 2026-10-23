<?php

namespace App\Enums;

/**
 * Lifecycle of an event.
 *
 * Backing the column with an enum keeps the status values out of free-form
 * strings and gives every layer (validation, policy, UI) one source of truth.
 */
enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /**
     * Human readable label used in the admin UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Dipublikasikan',
            self::Closed => 'Ditutup',
        };
    }

    /**
     * Map the status onto a design-system badge variant.
     */
    public function badgeVariant(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Published => 'success',
            self::Closed => 'warning',
        };
    }

    /**
     * Whether participants may place orders for an event in this status.
     *
     * Only published events are orderable. Draft events are still being
     * prepared and closed events no longer accept orders (PRD FR-02).
     */
    public function allowsOrdering(): bool
    {
        return $this === self::Published;
    }

    /**
     * Values for validation rules and <select> options.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
