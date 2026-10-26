<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Delivers the e-ticket to the participant (PRD FR-09).
 *
 * Phase 13 attaches the ticket as a PDF, so the participant can show it at the
 * gate without a working connection and the message stays useful after the
 * status link is lost. The link is still included: it opens the live page, which
 * reflects a check-in that has already happened, while the PDF is a snapshot.
 *
 * The PDF is rendered by the caller rather than in attachments(). Rendering
 * happens before the message is handed to the transport, so a renderer failure
 * surfaces inside TicketMailer's try/catch and is recorded against the ticket
 * instead of failing somewhere the delivery trail cannot see.
 */
class TicketIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $pdf,
        public readonly string $pdfFileName,
    ) {}

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

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn (): string => $this->pdf, $this->pdfFileName)
                ->withMime('application/pdf'),
        ];
    }
}
