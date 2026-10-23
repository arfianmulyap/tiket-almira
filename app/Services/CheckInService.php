<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Ticket;
use App\Models\User;
use App\Support\CheckInOutcome;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Validates a scanned QR token and admits the participant (PRD FR-10).
 *
 * The whole class exists to answer one question safely: may this token be
 * used right now? The scanning device is untrusted and the same QR is often
 * scanned twice in a second, either because the camera fires repeatedly or
 * because two officers scan the same person at two gates.
 *
 * Four layers stop that producing two successful check-ins:
 *   1. the token is resolved to a ticket server-side, never trusted as an id,
 *   2. the ticket row is locked and its status re-read inside a transaction,
 *   3. the unique index on check_ins.ticket_id refuses a second insert,
 *   4. the duplicate-key violation is caught and reported as "already used"
 *      rather than as a server error.
 *
 * Layer 3 is what makes this correct rather than merely likely to work.
 */
class CheckInService
{
    /**
     * Length of a QR token as issued by TicketIssuanceService.
     *
     * Used only to discard obviously impossible payloads before touching the
     * database, so a scanner pointed at an unrelated barcode does not cause
     * a query per frame.
     */
    private const TOKEN_LENGTH = 64;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Attempt a check-in for the scanned payload.
     *
     * Never throws for a refused scan. Refusals are ordinary outcomes at a
     * gate and the officer needs to see which one occurred, so they are
     * returned as a CheckInOutcome.
     */
    public function attempt(string $token, User $operator): CheckInOutcome
    {
        $token = trim($token);

        if (! $this->isPlausibleToken($token)) {
            return CheckInOutcome::invalid();
        }

        $ticket = Ticket::query()
            ->with(['order.event'])
            ->where('qr_token', $token)
            ->first();

        if ($ticket === null) {
            return CheckInOutcome::invalid();
        }

        // Void is reported separately from "already used" because the two
        // require different handling at the gate: a void ticket means send
        // the participant to an admin, a used one means check for a
        // duplicate.
        if ($ticket->status === TicketStatus::Void) {
            return CheckInOutcome::void($ticket);
        }

        return $this->admit($ticket, $operator);
    }

    /**
     * Record the check-in under a row lock.
     *
     * The status is re-read from the locked row rather than taken from the
     * model loaded above: between that read and this transaction another
     * scan may already have admitted the same ticket. A second concurrent
     * request blocks on the lock and then finds the status is no longer
     * VALID.
     */
    private function admit(Ticket $ticket, User $operator): CheckInOutcome
    {
        try {
            return DB::transaction(function () use ($ticket, $operator): CheckInOutcome {
                $locked = Ticket::query()
                    ->whereKey($ticket->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->allowsCheckIn()) {
                    // Already admitted, or voided between the read and the
                    // lock. Load the existing record so the officer sees
                    // when it was used.
                    return CheckInOutcome::alreadyUsed(
                        $ticket,
                        $this->existingCheckIn($locked),
                    );
                }

                $checkIn = new CheckIn;

                $checkIn->ticket_id = $locked->getKey();
                $checkIn->checked_in_by = $operator->getKey();
                $checkIn->operator_name = $operator->name;
                $checkIn->checked_in_at = now();

                $checkIn->save();

                // The ticket status is maintained alongside the check-in row
                // so "is this ticket used" is answerable without a join, and
                // both become visible in the same commit. There is no window
                // in which a check-in exists behind a ticket that still
                // reads as VALID.
                $locked->status = TicketStatus::CheckedIn;
                $locked->save();

                $this->audit->record(
                    $locked->order,
                    AuditAction::TicketCheckedIn,
                    $operator,
                    [
                        'ticket_id' => $locked->getKey(),
                        'check_in_id' => $checkIn->getKey(),
                        'order_reference' => $locked->order->order_reference,
                    ],
                );

                $ticket->setRawAttributes($locked->getAttributes());
                $ticket->syncOriginal();

                return CheckInOutcome::admitted($ticket, $checkIn);
            });
        } catch (QueryException $exception) {
            // The unique index on check_ins.ticket_id fired. Only reachable
            // if a concurrent transaction committed in a way the lock did
            // not serialise; reported as a normal refusal because from the
            // officer's point of view that is exactly what it is.
            if (! $this->isUniqueViolation($exception)) {
                throw $exception;
            }

            $ticket->refresh();

            return CheckInOutcome::alreadyUsed(
                $ticket,
                $this->existingCheckIn($ticket),
            );
        }
    }

    /**
     * The check-in already recorded for this ticket, if any.
     */
    private function existingCheckIn(Ticket $ticket): ?CheckIn
    {
        return CheckIn::query()
            ->with('operator')
            ->where('ticket_id', $ticket->getKey())
            ->first();
    }

    /**
     * Cheap shape check before querying.
     *
     * Deliberately not a security control: the real check is that the token
     * must match a stored ticket. This only avoids a database round trip for
     * payloads that cannot possibly be one of our tokens.
     */
    private function isPlausibleToken(string $token): bool
    {
        return strlen($token) === self::TOKEN_LENGTH
            && ctype_alnum($token);
    }

    /**
     * Whether the failure was a duplicate-key violation.
     *
     * SQLSTATE 23000 is the integrity-constraint class, which is portable
     * across MySQL and SQLite and avoids matching on driver error text.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23000';
    }
}
