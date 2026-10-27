<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\WhatsAppReminderService;
use Illuminate\Console\Command;

/**
 * Scheduled pass for the Phase 14 WhatsApp payment reminders.
 *
 * The command is intentionally thin: all timing and relevance rules live in
 * WhatsAppReminderService, so running it manually, too often, or from a second
 * scheduler cannot produce a duplicate or irrelevant reminder.
 */
class SendWhatsAppPaymentReminders extends Command
{
    protected $signature = 'whatsapp:send-payment-reminders';

    protected $description = 'Kirim pengingat WhatsApp menit ke-30 dan ke-50 untuk pesanan yang belum membayar';

    public function handle(WhatsAppReminderService $service): int
    {
        if (! config('whatsapp.enabled')) {
            $this->info('Integrasi WhatsApp belum diaktifkan (WHATSAPP_ENABLED=false).');

            return self::SUCCESS;
        }

        $stats = $service->dispatch();

        $this->info(sprintf(
            'Pengingat WhatsApp: %d baru, %d terkirim, %d gagal, %d dilewati.',
            $stats['created'],
            $stats['sent'],
            $stats['failed'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
