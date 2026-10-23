<?php

namespace App\Http\Controllers\Participant;

use App\Exceptions\EventNotOrderableException;
use App\Exceptions\QuotaUnavailableException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Event;
use App\Models\Order;
use App\Services\OrderExpiryService;
use App\Services\OrderService;
use App\Services\PaymentMethodRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly OrderExpiryService $expiry,
        private readonly PaymentMethodRegistry $paymentMethods,
    ) {}

    /**
     * Create one order for one ticket.
     *
     * The event is resolved through published() so an order can never be
     * placed against a draft or closed event by posting its slug directly.
     */
    public function store(StoreOrderRequest $request, string $slug): RedirectResponse
    {
        $event = Event::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        try {
            $order = $this->orders->create($event, $request->participantData());
        } catch (QuotaUnavailableException $exception) {
            // Sold out between rendering the form and submitting it. The
            // input is returned so the participant does not retype it in
            // case another slot is released.
            return back()
                ->withInput()
                ->with('error', $exception->getMessage());
        } catch (EventNotOrderableException $exception) {
            return redirect()
                ->route('events.index')
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('orders.show', $order->access_token)
            ->with('success', 'Pesanan berhasil dibuat. Selesaikan pembayaran sebelum tenggat.');
    }

    /**
     * Show the status of one order.
     *
     * Authorisation is the token in the URL: it is 64 random characters and
     * is the participant's only credential, since the MVP has no participant
     * accounts. The lookup is by token alone, so one participant can never
     * reach another participant's order by guessing an id or a reference
     * (PRD FR-04). PRD open question 6 asks for a final mechanism; this is
     * recorded as an assumption pending that decision.
     */
    public function show(string $token): View
    {
        $order = Order::query()
            ->with(['event', 'ticket'])
            ->where('access_token', $token)
            ->firstOrFail();

        // Correct the status on read instead of waiting for the scheduler.
        // The sweep runs every minute, but it may be stopped or lagging, and
        // showing "Menunggu Pembayaran" with a dead countdown would invite
        // the participant to pay for a slot they no longer hold. The service
        // only acts when the order really is overdue and unpaid, so this is
        // a no-op in the normal case.
        if ($order->status->awaitsProof() && $order->hasExpired()) {
            $this->expiry->expire($order);
        }

        return view('participant.orders.show', [
            'order' => $order,
            // Only methods this environment may actually offer. In production
            // with no confirmed methods this is empty and the view falls back
            // to a labelled "not yet available" state rather than inventing
            // instructions (PRD section 15 questions 1 and 2).
            'paymentMethods' => $this->paymentMethods->available(),
            'showsDummyPaymentData' => $this->paymentMethods->showsDummyData(),
            // Derived from config so the hint, the accept attribute, and the
            // validation rules can never disagree about the limits.
            'proofMaxMegabytes' => round(((int) config('payments.proof.max_kilobytes', 4096)) / 1024, 1),
            'proofFormatHint' => Str::upper(implode(', ', config('payments.proof.mimes', []))),
            'proofAcceptAttribute' => collect(config('payments.proof.mimes', []))
                ->map(fn (string $mime) => '.'.$mime)
                ->implode(','),
        ]);
    }
}
