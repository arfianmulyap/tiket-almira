<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\EmailDeliveryStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\AuditLogger;
use App\Services\TicketMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resend an e-ticket email (Phase 13).
 *
 * The phase's completion criterion is "email dapat dikirim ulang tanpa membuat
 * tiket atau QR baru", so this action deliberately never reaches
 * TicketIssuanceService: it takes the ticket that already exists and sends it
 * again. The participant keeps the same QR code, and every copy of the ticket —
 * the original email, this one, and the PDF inside both — stays valid.
 *
 * Both the mail attempt and the decision to resend are recorded, in different
 * places on purpose: `email_deliveries` answers "did it go out?", while the audit
 * trail answers "who sent a participant's ticket, and when?".
 */
class TicketEmailController extends Controller
{
    public function __construct(
        private readonly TicketMailer $mailer,
        private readonly AuditLogger $audit,
    ) {}

    public function store(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('resendTicket', $order);

        $order->loadMissing('ticket');

        $ticket = $order->ticket;

        // OrderPolicy::resendTicket() already required a ticket, but the action
        // does not depend on that detail being impossible to change.
        if ($ticket === null) {
            return back()->with('error', 'Pesanan ini belum memiliki tiket untuk dikirim.');
        }

        $delivery = $this->mailer->send($ticket, $request->user());

        $this->audit->record($ticket, AuditAction::TicketEmailResent, $request->user(), [
            'order_reference' => $order->order_reference,
            'recipient_email' => $delivery->recipient_email,
            'status' => $delivery->status->value,
        ]);

        return back()->with(
            $delivery->status->isDelivered() ? 'success' : 'warning',
            match ($delivery->status) {
                EmailDeliveryStatus::Sent => "E-ticket dikirim ulang ke {$delivery->recipient_email}.",
                EmailDeliveryStatus::Skipped => 'Email e-ticket belum diaktifkan (TICKET_EMAIL_ENABLED=false), jadi tidak ada email yang dikirim.',
                EmailDeliveryStatus::Failed => 'Email e-ticket gagal dikirim. Penyebabnya tercatat di riwayat pengiriman di bawah.',
            },
        );
    }
}
