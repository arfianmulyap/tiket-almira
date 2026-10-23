<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\TicketStatus;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Issues the e-ticket for an approved order.
 *
 * Issuance is idempotent: PRD FR-09 and business rule 10 require that one
 * order yields at most one ticket, even if the approve action is replayed by
 * a double-clicked form, a retried request, or two admins acting at once.
 *
 * Three independent layers enforce that:
 *   1. an existing-ticket check before any insert,
 *   2. the caller holding a row lock on the order (PaymentVerificationService),
 *   3. a unique index on tickets.order_id as the final backstop.
 */
class TicketIssuanceService
{
    /**
     * Length of the QR token. 64 characters from Str::random(), which draws
     * on a cryptographically secure source, so the token is unguessable and
     * is safe to be the only credential the scanner needs (PRD section 11).
     */
    private const TOKEN_LENGTH = 64;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Return the order's ticket, creating it only if none exists yet.
     *
     * Must be called inside the transaction that approves the payment so the
     * ticket and the PAID status commit together.
     */
    public function issue(Order $order, ?User $actor = null): Ticket
    {
        // Queried rather than read off the relation so a model loaded before
        // a concurrent approval cannot report a stale "no ticket".
        $existing = Ticket::query()->where('order_id', $order->getKey())->first();

        if ($existing !== null) {
            return $existing;
        }

        $ticket = new Ticket;

        $ticket->order_id = $order->getKey();
        $ticket->qr_token = $this->generateToken();
        $ticket->status = TicketStatus::Valid;
        $ticket->issued_at = now();

        $ticket->save();

        $this->audit->record($order, AuditAction::TicketIssued, $actor, [
            'ticket_id' => $ticket->getKey(),
            'order_reference' => $order->order_reference,
        ]);

        return $ticket;
    }

    /**
     * Build a QR token that is unique across tickets.
     *
     * The unique index is the real guarantee; this loop only avoids hitting
     * it on the astronomically unlikely collision.
     */
    private function generateToken(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (Ticket::query()->where('qr_token', $token)->exists());

        return $token;
    }
}
