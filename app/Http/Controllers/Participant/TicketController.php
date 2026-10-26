<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\QrCodeRenderer;
use App\Services\TicketPdfRenderer;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketController extends Controller
{
    public function __construct(
        private readonly QrCodeRenderer $qr,
        private readonly TicketPdfRenderer $pdf,
    ) {}

    /**
     * Show the e-ticket for one order.
     *
     * Reached with the order's access token, the same 64 character secret
     * that guards the status page, because the MVP has no participant
     * accounts (PRD FR-09 "akses terlindungi", open question 6).
     *
     * The ticket is looked up through the order rather than by its own QR
     * token on purpose: the QR token is the credential the scanner validates,
     * and putting it in a browser URL would leak it into history, logs, and
     * shared links.
     */
    public function show(string $token): View
    {
        $order = $this->orderFor($token);

        return view('participant.tickets.show', [
            'order' => $order,
            'ticket' => $order->ticket,
            // Only the opaque token is encoded, never personal data
            // (PRD FR-09).
            'qrSvg' => $this->qr->render($order->ticket->qr_token),
        ]);
    }

    /**
     * Download the same e-ticket as a PDF (Phase 13).
     *
     * Guarded by the same access token as the on-screen ticket, so the PDF never
     * becomes a second, weaker way in. The code inside is the ticket's real QR
     * token, which is exactly what the attached email contains.
     */
    public function pdf(string $token): Response
    {
        $order = $this->orderFor($token);
        $ticket = $order->ticket;

        $content = $this->pdf->render($ticket);

        // Rendered per request from the database rather than stored: no stale
        // copy can drift from the ticket's real status, and there is no private
        // file to clean up or accidentally expose.
        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->pdf->fileName($ticket).'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    /**
     * Resolve the order behind an access token, requiring an issued ticket.
     *
     * 404 rather than 403: until the payment is approved there is no ticket to
     * speak of, and the response should not reveal anything about an order to
     * someone holding a token for a different one.
     */
    private function orderFor(string $token): Order
    {
        $order = Order::query()
            ->with(['event', 'ticket'])
            ->where('access_token', $token)
            ->firstOrFail();

        if ($order->ticket === null) {
            throw new NotFoundHttpException;
        }

        return $order;
    }
}
