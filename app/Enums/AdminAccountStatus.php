<?php

namespace App\Enums;

/**
 * Lifecycle of an admin account (Phase 11).
 *
 * A newly invited admin is `Invited` and has no password at all, so the account
 * cannot be used before the invitation is accepted — the requirement is
 * enforced by the schema and the login flow, not only by the UI. `Inactive` is
 * a revocation an admin performed, and can be reversed by reactivating the
 * account because the password is still on file.
 */
enum AdminAccountStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Menunggu Undangan',
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::Invited => 'warning',
            self::Active => 'success',
            self::Inactive => 'neutral',
        };
    }

    /**
     * Only an active account may sign in or open the panel.
     */
    public function canUsePanel(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
