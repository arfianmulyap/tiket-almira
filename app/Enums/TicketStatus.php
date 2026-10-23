<?php

namespace App\Enums;

/**
 * Lifecycle of an issued ticket, following PRD section 9.
 */
enum TicketStatus: string
{
    case Valid = 'VALID';
    case CheckedIn = 'CHECKED_IN';
    case Void = 'VOID';

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Berlaku',
            self::CheckedIn => 'Sudah Check-in',
            self::Void => 'Dibatalkan',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::CheckedIn => 'info',
            self::Void => 'neutral',
        };
    }

    /**
     * Whether a ticket in this status may still be used to check in.
     *
     * Phase 08 builds the scanner on top of this, so the "one valid check-in
     * per token" rule (PRD business rule 11) has a single definition.
     */
    public function allowsCheckIn(): bool
    {
        return $this === self::Valid;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
