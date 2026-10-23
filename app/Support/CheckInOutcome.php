<?php

namespace App\Support;

use App\Enums\CheckInResult;
use App\Models\CheckIn;
use App\Models\Ticket;

/**
 * The result of one scan attempt, together with whatever context the gate
 * screen is allowed to show.
 *
 * Returned instead of throwing for the refusal cases. A ticket that is
 * already checked in is not an exceptional condition: at a real gate it is
 * routine, and the officer needs to be told *when* it was used and *by whom*
 * it was admitted. Exceptions would force that detail into a message string.
 *
 * Only ever built through the named constructors, so an outcome cannot claim
 * success while carrying no check-in record.
 */
final readonly class CheckInOutcome
{
    private function __construct(
        public CheckInResult $result,
        public ?Ticket $ticket = null,
        public ?CheckIn $checkIn = null,
    ) {}

    /**
     * The participant was admitted just now.
     */
    public static function admitted(Ticket $ticket, CheckIn $checkIn): self
    {
        return new self(CheckInResult::Success, $ticket, $checkIn);
    }

    /**
     * The token is valid but the ticket was already used.
     *
     * The existing check-in is carried so the officer can see when it
     * happened and decide whether this is a duplicate QR or a genuine
     * mistake (PRD FR-10).
     */
    public static function alreadyUsed(Ticket $ticket, ?CheckIn $checkIn): self
    {
        return new self(CheckInResult::AlreadyCheckedIn, $ticket, $checkIn);
    }

    /**
     * The ticket exists but has been deactivated.
     */
    public static function void(Ticket $ticket): self
    {
        return new self(CheckInResult::TicketVoid, $ticket);
    }

    /**
     * No ticket matched the scanned payload.
     *
     * Carries no ticket by construction, which is what stops the response
     * from leaking anything about a token the scanner does not already hold.
     */
    public static function invalid(): self
    {
        return new self(CheckInResult::InvalidToken);
    }

    public function isSuccessful(): bool
    {
        return $this->result->isSuccessful();
    }

    /**
     * Payload for the scanner's JSON response.
     *
     * Participant name and event are included only when a ticket actually
     * matched, because the officer has to verify the person in front of them
     * against the ticket. Nothing else about the order is exposed: not the
     * email, the phone number, the price, nor the QR token itself.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result->value,
            'label' => $this->result->label(),
            'description' => $this->result->description(),
            'variant' => $this->result->alertVariant(),
            'participant_name' => $this->ticket?->order->participant_name,
            'event_name' => $this->ticket?->order->event->name,
            'order_reference' => $this->ticket?->order->order_reference,
            'checked_in_at' => $this->checkIn?->checked_in_at->translatedFormat('d M Y, H:i'),
            'operator_name' => $this->checkIn?->operatorName(),
        ];
    }
}
