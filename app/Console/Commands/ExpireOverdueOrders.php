<?php

namespace App\Console\Commands;

use App\Services\OrderExpiryService;
use Illuminate\Console\Command;

/**
 * Scheduled sweep that expires unpaid orders past their deadline.
 *
 * Safe to run at any time and as often as needed: OrderExpiryService only
 * transitions orders that are still PENDING_PAYMENT and already overdue, so
 * repeat runs are no-ops (PRD FR-08, "dapat dijalankan ulang tanpa merusak
 * data").
 */
class ExpireOverdueOrders extends Command
{
    protected $signature = 'orders:expire-overdue';

    protected $description = 'Tandai pesanan yang melewati batas pembayaran 60 menit sebagai kedaluwarsa';

    public function handle(OrderExpiryService $service): int
    {
        $expired = $service->sweep();

        if ($expired === 0) {
            $this->info('Tidak ada pesanan yang perlu dikedaluwarsakan.');

            return self::SUCCESS;
        }

        $this->info("{$expired} pesanan ditandai kedaluwarsa.");

        return self::SUCCESS;
    }
}
