<?php

namespace App\Http\Controllers\Participant;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\QrCodeRenderer;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TicketController extends Controller
{
    public function __construct(private readonly QrCodeRenderer $qr) {}

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
        $order = Order::query()
            ->with(['event', 'ticket'])
            ->where('access_token', $token)
            ->firstOrFail();

        // 404 rather than 403: until the payment is approved there is no
        // ticket to speak of, and the response should not reveal anything
        // about an order to someone holding a token for a different one.
        if ($order->ticket === null) {
            throw new NotFoundHttpException;
        }

        return view('participant.tickets.show', [
            'order' => $order,
            'ticket' => $order->ticket,
            // Only the opaque token is encoded, never personal data
            // (PRD FR-09).
            'qrSvg' => $this->qr->render($order->ticket->qr_token),
        ]);
    }
}
