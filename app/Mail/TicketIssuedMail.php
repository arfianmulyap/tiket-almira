<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers the e-ticket link to the participant (PRD FR-09).
 *
 * The QR image is not attached. The mail carries the secret ticket URL and
 * the code is rendered on that page, so the token is not duplicated into
 * mailbox copies and forwarded messages any more than necessary.
 */
class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Ticket $ticket) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->ticket->order->participant_email],
            subject: 'E-ticket '.$this->ticket->order->event->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.ticket-issued',
            with: [
                'order' => $this->ticket->order,
                'event' => $this->ticket->order->event,
                'ticketUrl' => route('tickets.show', $this->ticket->order->access_token),
            ],
        );
    }
}
