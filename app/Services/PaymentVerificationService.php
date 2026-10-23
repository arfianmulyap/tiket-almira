<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Exceptions\OrderNotDecidableException;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Approves or rejects a manual payment (PRD FR-07).
 *
 * Both actions follow the same shape: open a transaction, take a row lock on
 * the order, re-read its status under that lock, and only then act. The
 * re-read is the important part. An admin's browser may have rendered the
 * verification page minutes ago, and in between the order could have been
 * decided by another admin. Checking the status the controller loaded would
 * let the second decision silently overwrite the first.
 */
class PaymentVerificationService
{
    public function __construct(
        private readonly TicketIssuanceService $tickets,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Approve the payment and issue exactly one ticket.
     *
     * Returns the ticket so the caller can link straight to it. Replaying an
     * approval on an already-approved order is refused by the status check,
     * and even if that were bypassed the issuance service and the unique
     * index on tickets.order_id would still prevent a duplicate.
     *
     * @throws OrderNotDecidableException
     */
    public function approve(Order $order, User $actor): Ticket
    {
        return DB::transaction(function () use ($order, $actor): Ticket {
            $locked = $this->lockForDecision($order);

            $locked->status = OrderStatus::Paid;
            $locked->reviewed_by = $actor->getKey();
            $locked->reviewed_at = now();
            $locked->rejection_reason = null;

            $locked->save();

            $this->audit->record($locked, AuditAction::PaymentApproved, $actor, [
                'order_reference' => $locked->order_reference,
                'amount' => $locked->ticket_price_snapshot,
            ]);

            // Inside the same transaction: the PAID status and the ticket
            // must become visible together, so there is no window in which
            // an order reads as paid with no ticket behind it.
            $ticket = $this->tickets->issue($locked, $actor);

            $order->setRawAttributes($locked->getAttributes());
            $order->syncOriginal();

            return $ticket;
        });
    }

    /**
     * Reject the payment, recording the reason.
     *
     * No ticket is issued (PRD FR-07). The quota slot is released as a
     * consequence of the status alone: OrderStatus::holdsQuota() returns
     * false for REJECTED and Event::reservedQuota() derives the count from
     * the orders table, so nothing has to be decremented here.
     *
     * Whether the participant may then upload a new proof is an open
     * business decision (PRD section 15 question 3), so no re-upload path is
     * created by this method.
     *
     * @throws OrderNotDecidableException
     */
    public function reject(Order $order, User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason): Order {
            $locked = $this->lockForDecision($order);

            $locked->status = OrderStatus::Rejected;
            $locked->reviewed_by = $actor->getKey();
            $locked->reviewed_at = now();
            $locked->rejection_reason = $reason;

            $locked->save();

            $this->audit->record($locked, AuditAction::PaymentRejected, $actor, [
                'order_reference' => $locked->order_reference,
                'reason' => $reason,
            ]);

            $order->setRawAttributes($locked->getAttributes());
            $order->syncOriginal();

            return $order;
        });
    }

    /**
     * Re-read the order under a row lock and confirm it may still be decided.
     *
     * lockForUpdate() holds the row until the surrounding transaction
     * commits, so a second concurrent decision blocks here and then finds the
     * status already changed.
     *
     * @throws OrderNotDecidableException
     */
    private function lockForDecision(Order $order): Order
    {
        $locked = Order::query()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $locked->awaitsDecision()) {
            throw OrderNotDecidableException::make();
        }

        return $locked;
    }
}
