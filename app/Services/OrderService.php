<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Exceptions\EventNotOrderableException;
use App\Exceptions\QuotaUnavailableException;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates orders and reserves exactly one quota slot per order.
 *
 * All of the business rules that must not be negotiable live here rather
 * than in the controller: the price snapshot, the 60 minute deadline, the
 * order reference, and above all the quota reservation.
 */
class OrderService
{
    /**
     * Payment window in minutes (PRD business rule 5).
     */
    public const PAYMENT_WINDOW_MINUTES = 60;

    public function __construct(private readonly PaymentMethodRegistry $paymentMethods) {}

    /**
     * Create one order holding one quota slot.
     *
     * The whole thing runs in a transaction and starts by taking a row lock
     * on the event. Two concurrent requests for the last slot therefore
     * queue: the first commits, the second re-reads the now-updated count
     * and is rejected. Without the lock both would read "1 remaining" and
     * both would succeed, overselling the event (PRD business rule 15).
     *
     * @param  array{participant_name: string, participant_whatsapp: string, participant_email: string}  $participant
     *
     * @throws EventNotOrderableException
     * @throws QuotaUnavailableException
     */
    public function create(Event $event, array $participant): Order
    {
        return DB::transaction(function () use ($event, $participant): Order {
            // lockForUpdate() holds the event row until this transaction
            // commits. MySQL 8.4 supports SELECT ... FOR UPDATE, which is
            // what this compiles to.
            $lockedEvent = Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // Re-checked under the lock: the event may have been closed
            // after the form was rendered.
            if (! $lockedEvent->isOrderable()) {
                throw EventNotOrderableException::make();
            }

            // Counted under the lock, so this number cannot move before the
            // insert below commits.
            if ($lockedEvent->reservedQuota() >= $lockedEvent->quota) {
                throw QuotaUnavailableException::make();
            }

            $now = now();

            $order = new Order($participant);

            $order->event_id = $lockedEvent->id;
            $order->order_reference = $this->generateOrderReference();
            $order->access_token = $this->generateAccessToken();

            // Snapshot, not a live reference: later price edits must leave
            // this order untouched (PRD business rule 12).
            $order->ticket_price_snapshot = $lockedEvent->ticket_price;

            // Same rule for the instructions the participant is about to
            // follow. Editing a bank account or retiring a method changes
            // what new orders see, never what this order shows (Phase 12).
            $order->payment_instructions_snapshot = $this->paymentMethods->snapshot();

            $order->status = OrderStatus::PendingPayment;
            $order->expires_at = $now->copy()->addMinutes(self::PAYMENT_WINDOW_MINUTES);

            $order->save();

            return $order;
        });
    }

    /**
     * Build a unique, human-quotable order reference.
     *
     * Format: ALM-YYMMDD-XXXXXX. The date prefix helps admins reconcile
     * transfers by day; the random tail keeps it unique. The unique index on
     * the column is the real guarantee, this loop just avoids hitting it.
     */
    private function generateOrderReference(): string
    {
        do {
            $reference = sprintf(
                'ALM-%s-%s',
                now()->format('ymd'),
                Str::upper(Str::random(6))
            );
        } while (Order::query()->where('order_reference', $reference)->exists());

        return $reference;
    }

    /**
     * Build the secret that guards the order status page.
     *
     * 64 random characters from Str::random(), which uses a
     * cryptographically secure source. Long and unguessable so the URL can
     * safely be the participant's only credential (PRD section 11).
     */
    private function generateAccessToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Order::query()->where('access_token', $token)->exists());

        return $token;
    }
}
