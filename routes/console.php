<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Expiry of overdue orders (PRD FR-08)
|--------------------------------------------------------------------------
|
| Runs every minute so a participant never sees a stale "Menunggu
| Pembayaran" for long after the deadline. Note that quota is already
| correct between runs: Event::reservedQuota() stops counting an overdue
| order the moment its deadline passes, so a late or stopped scheduler
| cannot keep tickets off sale. This job only records the status.
|
| withoutOverlapping() prevents two sweeps running at once if one is slow,
| and runInBackground() keeps a slow sweep from delaying other scheduled
| work.
|
*/
Schedule::command('orders:expire-overdue')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

/*
|--------------------------------------------------------------------------
| WhatsApp payment reminders (Phase 14)
|--------------------------------------------------------------------------
|
| Runs every minute because the reminders are anchored to minute 30 and
| minute 50 of each order, not to fixed clock times. The service itself
| decides whether anything is due, so a manual run or a late scheduler
| cannot send a reminder twice or out of order.
|
| Nothing is sent while WHATSAPP_ENABLED=false. The default `log` driver
| writes to the log instead of contacting a provider; see config/whatsapp.php.
|
*/
Schedule::command('whatsapp:send-payment-reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
