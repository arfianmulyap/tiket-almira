<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

/**
 * Local-only gateway used until a real WhatsApp provider is chosen (Phase 14).
 *
 * It writes the exact message to the application log and reports success, so
 * the reminder scheduling, duplicate guard, retry limit, and admin screen can
 * all be exercised without credentials or an external call. The driver name is
 * stored on every delivery row, so the panel never pretends a provider was
 * contacted.
 *
 * The message contains the order's secure status link. That link is a
 * credential, so this driver must not be used in production and its log file
 * must be treated as sensitive.
 */
class LogWhatsAppGateway implements WhatsAppGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $recipient, string $message): WhatsAppGatewayResult
    {
        Log::info('WhatsApp (driver log): pesan tidak dikirim ke provider.', [
            'to' => $recipient,
            'message' => $message,
        ]);

        return WhatsAppGatewayResult::sent();
    }
}
