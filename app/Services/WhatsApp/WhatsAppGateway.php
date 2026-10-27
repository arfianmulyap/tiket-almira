<?php

namespace App\Services\WhatsApp;

/**
 * Contract every WhatsApp provider adapter must satisfy (Phase 14).
 *
 * The application deliberately knows nothing about a specific gateway's URL,
 * payload shape, or credentials (the provider has not been chosen yet). A real
 * adapter is one class implementing this interface plus an entry in
 * config/whatsapp.php under `drivers`.
 */
interface WhatsAppGateway
{
    /**
     * Send one plain-text message.
     *
     * Implementations must not throw for an ordinary provider rejection: they
     * should return WhatsAppGatewayResult::failed() so the reminder service can
     * record the failure and retry within its limit. Unexpected exceptions are
     * still caught by the service, but returning a result keeps the error
     * message useful.
     */
    public function send(string $recipient, string $message): WhatsAppGatewayResult;

    /**
     * Stable driver name recorded on each delivery row.
     */
    public function name(): string;
}
