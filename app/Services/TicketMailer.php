<?php

namespace App\Services;

use App\Mail\TicketIssuedMail;
use App\Models\Ticket;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends the e-ticket email without ever putting the ticket at risk.
 *
 * PRD FR-09 and section 11 are explicit: "kegagalan email tidak membatalkan
 * tiket yang sudah sah". So every failure is caught and logged here instead
 * of propagating. If this threw, it would roll back the approval transaction
 * and a participant who has genuinely paid would lose their ticket because a
 * mail server was briefly unreachable.
 *
 * The log entry is what the operator uses to resend (PRD FR-09 "mekanisme
 * pencatatan dan pengiriman ulang").
 */
class TicketMailer
{
    /**
     * Attempt delivery. Returns true only if the mail was handed to the
     * transport successfully.
     */
    public function send(Ticket $ticket): bool
    {
        if (! config('tickets.email_enabled')) {
            // Not an error: delivery is deliberately off until a mail
            // service is confirmed (PRD section 15 question 7).
            return false;
        }

        $ticket->loadMissing('order.event');

        try {
            Mail::send(new TicketIssuedMail($ticket));

            return true;
        } catch (Throwable $exception) {
            Log::error('Gagal mengirim e-ticket.', [
                'ticket_id' => $ticket->getKey(),
                'order_reference' => $ticket->order->order_reference,
                'participant_email' => $ticket->order->participant_email,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
