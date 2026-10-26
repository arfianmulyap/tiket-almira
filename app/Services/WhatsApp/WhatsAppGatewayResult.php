<?php

namespace App\Services\WhatsApp;

/**
 * Outcome of handing one message to a gateway (Phase 14).
 */
final readonly class WhatsAppGatewayResult
{
    private function __construct(
        public bool $successful,
        public ?string $error = null,
    ) {}

    public static function sent(): self
    {
        return new self(true);
    }

    public static function failed(?string $error = null): self
    {
        return new self(false, $error ?? 'Gateway tidak memberikan alasan kegagalan.');
    }
}
