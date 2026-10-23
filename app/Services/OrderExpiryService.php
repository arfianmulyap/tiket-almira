<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Expires orders whose 60 minute payment window has closed without a proof.
 *
 * Quota is never stored as a counter. Event::reservedQuota() derives it from
 * the orders table, and scopeHoldingQuota() already stops counting an
 * overdue PENDING_PAYMENT order the moment its deadline passes. So this
 * service does not "release" anything: it only records the terminal status
 * that the quota count has effectively assumed since minute 60.
 *
 * That design is what makes the double-release bug in PRD FR-08 structurally
 * impossible. There is no counter to decrement twice.
 */
class OrderExpiryService
{
    /**
     * Rows per UPDATE. Keeps each statement's lock footprint small so the
     * sweep cannot block participants ordering on a busy event.
     */
    public const BATCH_SIZE = 500;

    /**
     * Flip every overdue unpaid order to EXPIRED.
     *
     * Returns the number of orders actually transitioned, which is what the
     * command reports and what the tests assert on.
     */
    public function sweep(): int
    {
        $expired = 0;

        // Re-queried every iteration rather than paginated: each UPDATE
        // removes rows from the result set, so offsets would skip records.
        while (true) {
            $ids = Order::query()
                ->overdue()
                ->orderBy('id')
                ->limit(self::BATCH_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $expired += $this->expireBatch($ids->all());
        }

        return $expired;
    }

    /**
     * Expire one order, used when a single order is known to be overdue.
     *
     * Returns true only if this call is the one that changed the status, so
     * callers can tell an actual transition from a no-op.
     */
    public function expire(Order $order): bool
    {
        $changed = $this->expireBatch([$order->getKey()]) === 1;

        if ($changed) {
            $order->refresh();
        }

        return $changed;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function expireBatch(array $ids): int
    {
        return DB::transaction(function () use ($ids): int {
            // The status condition is repeated inside the UPDATE on purpose.
            // Between the SELECT above and this statement a participant may
            // have uploaded a proof, moving the order to
            // PENDING_VERIFICATION. Filtering on status here means the
            // database itself refuses to expire it, so a proof submitted
            // just before the deadline can never be destroyed by the sweep.
            //
            // It also makes the sweep idempotent: a second run matches
            // nothing, because the rows are no longer PENDING_PAYMENT.
            return Order::query()
                ->whereIn('id', $ids)
                ->where('status', OrderStatus::PendingPayment)
                ->where('expires_at', '<=', now())
                ->update([
                    'status' => OrderStatus::Expired,
                    'updated_at' => now(),
                ]);
        });
    }
}
