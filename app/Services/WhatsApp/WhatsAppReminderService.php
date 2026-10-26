<?php

namespace App\Services\WhatsApp;

use App\Enums\OrderStatus;
use App\Enums\WhatsAppDeliveryStatus;
use App\Enums\WhatsAppReminderStage;
use App\Models\Order;
use App\Models\WhatsAppDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Queues and sends the minute 30 and minute 50 payment reminders (Phase 14).
 *
 * Every rule that makes a reminder valid is applied here, not by the command:
 *
 * - the order must still be PENDING_PAYMENT (so a proof has not moved it on);
 * - no payment row may exist;
 * - the deadline must not have passed;
 * - a reminder stage that has been superseded by the next one is not sent.
 *
 * That last rule is why a late scheduler cannot send both reminders at once:
 * an order first seen at minute 55 gets only the minute 50 message, and a
 * minute 30 row that never left PENDING/FAILED is closed as SKIPPED instead of
 * being delivered out of context.
 */
class WhatsAppReminderService
{
    /**
     * Rows per query, keeping each scheduler run's memory footprint small.
     */
    public const BATCH_SIZE = 200;

    public function __construct(private readonly WhatsAppGateway $gateway) {}

    /**
     * Run one scheduler pass.
     *
     * @return array{created: int, sent: int, failed: int, skipped: int}
     */
    public function dispatch(): array
    {
        $stats = [
            'created' => 0,
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        if (! config('whatsapp.enabled')) {
            return $stats;
        }

        // Close stale work first, so a stage that is no longer relevant is
        // never picked up by the delivery pass in the same run.
        $stats['skipped'] = $this->closeIrrelevantDeliveries();
        $stats['created'] = $this->queueDueReminders();

        $outcome = $this->deliverPending();

        $stats['sent'] = $outcome['sent'];
        $stats['failed'] = $outcome['failed'];
        $stats['skipped'] += $outcome['skipped'];

        return $stats;
    }

    /**
     * Create the delivery row for every order whose next reminder is due.
     *
     * Splitting the two stages into separate windows is what makes a reminder
     * that was missed entirely stay missed: an order created 55 minutes ago is
     * outside the minute 30 window, so it can only receive the minute 50
     * message. It is never sent both at once.
     */
    private function queueDueReminders(): int
    {
        $now = now();
        $created = 0;

        $windows = [
            // stage, created_at must be at or before, created_at must be after
            [WhatsAppReminderStage::Minute30, $now->copy()->subMinutes(30), $now->copy()->subMinutes(50)],
            [WhatsAppReminderStage::Minute50, $now->copy()->subMinutes(50), null],
        ];

        foreach ($windows as [$stage, $dueAt, $notBefore]) {
            Order::query()
                ->where('status', OrderStatus::PendingPayment)
                ->where('expires_at', '>', $now)
                ->where('created_at', '<=', $dueAt)
                ->when(
                    $notBefore !== null,
                    fn (Builder $query) => $query->where('created_at', '>', $notBefore),
                )
                ->whereDoesntHave('payment')
                ->whereDoesntHave(
                    'whatsappDeliveries',
                    fn (Builder $query) => $query->where('kind', $stage->value),
                )
                ->chunkById(self::BATCH_SIZE, function ($orders) use ($stage, &$created): void {
                    foreach ($orders as $order) {
                        $delivery = WhatsAppDelivery::query()->firstOrCreate(
                            [
                                'order_id' => $order->getKey(),
                                'kind' => $stage->value,
                            ],
                            [
                                'recipient_phone' => $this->normalizePhone((string) $order->participant_whatsapp),
                                'status' => WhatsAppDeliveryStatus::Pending,
                                'attempts' => 0,
                            ],
                        );

                        if ($delivery->wasRecentlyCreated) {
                            $created++;
                        }
                    }
                });
        }

        return $created;
    }

    /**
     * Close reminders that must not be sent any more.
     *
     * Only unfinished rows are touched. A FAILED row that has already used all
     * of its attempts stays FAILED as a record of what happened; it is not
     * rewritten to SKIPPED just because the order later moved on.
     */
    private function closeIrrelevantDeliveries(): int
    {
        $skipped = 0;
        $maxAttempts = $this->maxAttempts();

        WhatsAppDelivery::query()
            ->where(function (Builder $query) use ($maxAttempts): void {
                $query
                    ->where('status', WhatsAppDeliveryStatus::Pending)
                    ->orWhere(function (Builder $query) use ($maxAttempts): void {
                        $query
                            ->where('status', WhatsAppDeliveryStatus::Failed)
                            ->where('attempts', '<', $maxAttempts);
                    });
            })
            ->with(['order.payment', 'order.event'])
            ->chunkById(self::BATCH_SIZE, function ($deliveries) use (&$skipped): void {
                foreach ($deliveries as $delivery) {
                    $reason = $this->irrelevanceReason($delivery);

                    if ($reason === null) {
                        continue;
                    }

                    $this->markSkipped($delivery, $reason);
                    $skipped++;
                }
            });

        return $skipped;
    }

    /**
     * Attempt every unfinished, still-relevant delivery.
     *
     * The attempts column is claimed with an atomic UPDATE before the gateway
     * is called. Two scheduler processes therefore cannot both send the same
     * row, even if withoutOverlapping() is ever removed.
     *
     * @return array{sent: int, failed: int, skipped: int}
     */
    private function deliverPending(): array
    {
        $outcome = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $maxAttempts = $this->maxAttempts();

        WhatsAppDelivery::query()
            ->where(function (Builder $query) use ($maxAttempts): void {
                $query
                    ->where('status', WhatsAppDeliveryStatus::Pending)
                    ->orWhere(function (Builder $query) use ($maxAttempts): void {
                        $query
                            ->where('status', WhatsAppDeliveryStatus::Failed)
                            ->where('attempts', '<', $maxAttempts);
                    });
            })
            ->with(['order.payment', 'order.event'])
            ->chunkById(self::BATCH_SIZE, function ($deliveries) use (&$outcome, $maxAttempts): void {
                foreach ($deliveries as $delivery) {
                    $stage = $delivery->kind;

                    if (! $stage instanceof WhatsAppReminderStage) {
                        $this->markSkipped($delivery, 'Tahap pengingat tidak dikenali.');

                        continue;
                    }

                    if (($reason = $this->irrelevanceReason($delivery)) !== null) {
                        $this->markSkipped($delivery, $reason);
                        $outcome['skipped']++;

                        continue;
                    }

                    // Claim the attempt. The attempts value in the WHERE clause
                    // makes this a compare-and-set: a second process that read
                    // the same row before this update will match zero rows.
                    $claimed = WhatsAppDelivery::query()
                        ->whereKey($delivery->getKey())
                        ->whereIn('status', [
                            WhatsAppDeliveryStatus::Pending,
                            WhatsAppDeliveryStatus::Failed,
                        ])
                        ->where('attempts', $delivery->attempts)
                        ->where('attempts', '<', $maxAttempts)
                        ->update([
                            'attempts' => DB::raw('attempts + 1'),
                            'status' => WhatsAppDeliveryStatus::Pending->value,
                            'updated_at' => now(),
                        ]);

                    if ($claimed !== 1) {
                        continue;
                    }

                    $delivery->refresh();
                    $delivery->loadMissing(['order.payment', 'order.event']);

                    // The proof upload can win a race against the claim above.
                    // Re-checking here keeps a reminder from being sent after
                    // the participant has already done what it asks.
                    if (($reason = $this->irrelevanceReason($delivery)) !== null) {
                        $this->markSkipped($delivery, $reason);
                        $outcome['skipped']++;

                        continue;
                    }

                    $outcome[$this->send($delivery, $stage)]++;
                }
            });

        return $outcome;
    }

    /**
     * Hand one claimed delivery to the gateway and record the result.
     *
     * @return 'sent'|'failed'
     */
    private function send(WhatsAppDelivery $delivery, WhatsAppReminderStage $stage): string
    {
        $order = $delivery->order;
        $order->loadMissing('event');

        try {
            $result = $this->gateway->send(
                (string) $delivery->recipient_phone,
                $this->messageFor($order, $stage),
            );
        } catch (Throwable $exception) {
            // A provider outage must never break the scheduler. The attempt is
            // recorded and the next run may retry while the order is still
            // relevant.
            $result = WhatsAppGatewayResult::failed($exception->getMessage());
        }

        if ($result->successful) {
            $delivery->forceFill([
                'status' => WhatsAppDeliveryStatus::Sent,
                'driver' => $this->gateway->name(),
                'error_message' => null,
                'sent_at' => now(),
            ])->save();

            return 'sent';
        }

        $error = Str::limit(
            (string) ($result->error ?? 'Gateway menolak pesan.'),
            1000,
        );

        $delivery->forceFill([
            'status' => WhatsAppDeliveryStatus::Failed,
            'driver' => $this->gateway->name(),
            'error_message' => $error,
        ])->save();

        Log::warning('Pengingat WhatsApp gagal dikirim.', [
            'order_reference' => $order->order_reference,
            'kind' => $stage->value,
            'attempts' => $delivery->attempts,
            'error' => $error,
        ]);

        return 'failed';
    }

    /**
     * Why this reminder must not be sent, or null when it is still valid.
     *
     * The checks are ordered so the most actionable reason reaches the panel:
     * a proof that has already arrived is more useful to an operator than the
     * generic "status changed".
     */
    private function irrelevanceReason(WhatsAppDelivery $delivery): ?string
    {
        $order = $delivery->order;

        if (! $order instanceof Order) {
            return 'Pesanan tidak ditemukan.';
        }

        if ($this->hasPayment($order)) {
            return 'Bukti pembayaran sudah diunggah.';
        }

        if ($order->status !== OrderStatus::PendingPayment) {
            return 'Pesanan tidak lagi menunggu pembayaran.';
        }

        if ($order->hasExpired()) {
            return 'Batas pembayaran sudah lewat.';
        }

        if (
            $delivery->kind === WhatsAppReminderStage::Minute30
            && $order->created_at->lte(now()->subMinutes(WhatsAppReminderStage::Minute50->minute()))
        ) {
            return 'Pengingat menit ke-30 sudah lewat; pengingat menit ke-50 yang berlaku.';
        }

        return null;
    }

    private function markSkipped(WhatsAppDelivery $delivery, string $reason): void
    {
        $delivery->forceFill([
            'status' => WhatsAppDeliveryStatus::Skipped,
            'error_message' => $reason,
        ])->save();
    }

    /**
     * Whether a proof exists, reusing the eager-loaded relation when present.
     *
     * The scheduler loads `order.payment` in batches, so this avoids one query
     * per reminder while still being correct if the helper is called from a
     * context that did not eager load it.
     */
    private function hasPayment(Order $order): bool
    {
        return $order->relationLoaded('payment')
            ? $order->payment !== null
            : $order->payment()->exists();
    }

    /**
     * Build the participant-facing message. The status link uses the order's
     * access token, never the QR token: the QR token is the scanner credential
     * and must not travel in a chat message (PRD FR-09).
     */
    private function messageFor(Order $order, WhatsAppReminderStage $stage): string
    {
        $deadline = $order->expires_at->translatedFormat('d M Y, H:i').' WIB';
        $statusUrl = route('orders.show', $order->access_token);

        $warning = $stage === WhatsAppReminderStage::Minute30
            ? 'Kami belum menerima bukti pembayaran untuk pesanan ini.'
            : 'Batas pembayaran pesanan ini tinggal sedikit lagi dan bukti pembayaran belum kami terima.';

        return implode("\n", [
            "Halo {$order->participant_name},",
            '',
            "Pesanan {$order->order_reference} untuk {$order->event->name} masih menunggu pembayaran.",
            $warning,
            '',
            "Batas pembayaran: {$deadline}.",
            'Unggah bukti pembayaran sebelum tenggat melalui tautan aman berikut:',
            $statusUrl,
            '',
            'Abaikan pesan ini bila kamu sudah mengunggah bukti pembayaran.',
        ]);
    }

    /**
     * Store the phone in the 62... form expected by WhatsApp gateways.
     *
     * Order validation already normalises input to digits starting with 0 or
     * 62, so this only converts the local prefix. Unknown shapes are left as
     * they are and fail at the gateway rather than being dropped here.
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    private function maxAttempts(): int
    {
        return max(1, (int) config('whatsapp.reminders.max_attempts', 3));
    }
}
