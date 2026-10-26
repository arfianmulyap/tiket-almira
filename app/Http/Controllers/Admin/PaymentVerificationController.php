<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EmailDeliveryStatus;
use App\Exceptions\OrderNotDecidableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectPaymentRequest;
use App\Models\Order;
use App\Services\PaymentVerificationService;
use App\Services\TicketMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentVerificationController extends Controller
{
    public function __construct(
        private readonly PaymentVerificationService $verification,
        private readonly TicketMailer $mailer,
    ) {}

    /**
     * Approve the payment and issue the ticket.
     */
    public function approve(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('decide', $order);

        try {
            $ticket = $this->verification->approve($order, $request->user());
        } catch (OrderNotDecidableException $exception) {
            // Another admin decided this order between the page render and
            // this submit, or the form was submitted twice.
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', $exception->getMessage());
        }

        // Sent after the transaction has committed, never inside it. A mail
        // transport that blocks or throws must not hold a database lock or
        // roll back a valid approval (PRD FR-09).
        $delivery = $this->mailer->send($ticket, $request->user());

        // The approval itself always succeeded, so a delivery problem is a
        // warning rather than an error: the ticket exists and the participant
        // can still open it through the status page.
        $message = match ($delivery->status) {
            EmailDeliveryStatus::Sent => 'Pembayaran disetujui, tiket diterbitkan, dan e-ticket dikirim ke email peserta.',
            EmailDeliveryStatus::Skipped => 'Pembayaran disetujui dan tiket diterbitkan. Email e-ticket belum diaktifkan, jadi belum ada email yang dikirim.',
            EmailDeliveryStatus::Failed => 'Pembayaran disetujui dan tiket diterbitkan, tetapi email e-ticket gagal dikirim. Gunakan "Kirim Ulang Tiket" di detail pesanan.',
        };

        return redirect()
            ->route('admin.orders.show', $order)
            ->with($delivery->status->isDelivered() ? 'success' : 'warning', $message);
    }

    /**
     * Reject the payment, recording the reason.
     */
    public function reject(RejectPaymentRequest $request, Order $order): RedirectResponse
    {
        // Authorisation already ran in the form request, but it is repeated
        // here so the controller does not depend on that detail.
        $this->authorize('decide', $order);

        try {
            $this->verification->reject(
                $order,
                $request->user(),
                $request->validated()['rejection_reason'],
            );
        } catch (OrderNotDecidableException $exception) {
            return redirect()
                ->route('admin.orders.show', $order)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('admin.orders.show', $order)
            ->with('success', 'Pembayaran ditolak dan alasannya sudah dicatat.');
    }
}
