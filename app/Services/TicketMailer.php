<?php

namespace App\Services;

use App\Enums\EmailDeliveryStatus;
use App\Mail\TicketIssuedMail;
use App\Models\EmailDelivery;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the e-ticket email without ever putting the ticket at risk.
 *
 * PRD FR-09 and section 11 are explicit: "kegagalan email tidak membatalkan
 * tiket yang sudah sah". So every failure is caught and recorded here instead
 * of propagating. If this threw, it would roll back the approval transaction
 * and a participant who has genuinely paid would lose their ticket because a
 * mail server was briefly unreachable.
 *
 * Phase 13 turns the old boolean into a delivery record. The return value is the
 * attempt itself — SENT, FAILED, or SKIPPED — which the order screen and the
 * resend action read, and which makes a retry possible without reissuing the
 * ticket.
 */
class TicketMailer
{
    public function __construct(
        private readonly TicketPdfRenderer $pdf,
        private readonly EmailDeliveryLogger $deliveries,
    ) {}

    /**
     * Attempt delivery of $ticket and record the outcome.
     */
    public function send(Ticket $ticket, ?User $actor = null): EmailDelivery
    {
        $ticket->loadMissing('order.event');

        $recipientEmail = (string) $ticket->order->participant_email;

        if (! config('tickets.email_enabled')) {
            // Not an error: delivery is deliberately off until a mail service
            // is confirmed (PRD section 15 question 7). Recorded anyway so the
            // order screen can explain why nothing was sent.
            return $this->deliveries->record(
                $ticket,
                $recipientEmail,
                EmailDeliveryStatus::Skipped,
                $actor,
                'Email e-ticket belum diaktifkan (TICKET_EMAIL_ENABLED=false).',
            );
        }

        try {
            Mail::send(new TicketIssuedMail(
                $ticket,
                $this->pdf->render($ticket),
                $this->pdf->fileName($ticket),
            ));

            return $this->deliveries->record($ticket, $recipientEmail, EmailDeliveryStatus::Sent, $actor);
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim e-ticket.', [
                'ticket_id' => $ticket->getKey(),
                'order_reference' => $ticket->order->order_reference,
                'participant_email' => $recipientEmail,
                'exception' => $exception->getMessage(),
            ]);

            return $this->deliveries->record(
                $ticket,
                $recipientEmail,
                EmailDeliveryStatus::Failed,
                $actor,
                $exception->getMessage(),
            );
        }
    }
}
