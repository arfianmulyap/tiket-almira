<?php

namespace App\Http\Controllers\Participant;

use App\Exceptions\PaymentProofNotAcceptedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentProofRequest;
use App\Services\PaymentProofService;
use Illuminate\Http\RedirectResponse;

class PaymentProofController extends Controller
{
    public function __construct(private readonly PaymentProofService $proofs) {}

    /**
     * Accept one payment proof for an order (PRD FR-06).
     *
     * The order is resolved by its access token, the same 64 character secret
     * that guards the status page, so one participant cannot upload against
     * another participant's order.
     */
    public function store(StorePaymentProofRequest $request, string $token): RedirectResponse
    {
        $order = $request->order();

        abort_if($order === null, 404);

        try {
            $this->proofs->submit(
                $order,
                $request->paymentMethodFor($order),
                $request->file('proof'),
            );
        } catch (PaymentProofNotAcceptedException $exception) {
            // The deadline passed, or a proof already exists, between
            // rendering the form and submitting it. The status page below
            // will now show the real state.
            return redirect()
                ->route('orders.show', $token)
                ->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('orders.show', $token)
            // Deliberately does not say the payment is confirmed: an uploaded
            // proof is not proof that funds arrived (PRD business rule 8).
            ->with('success', 'Bukti pembayaran berhasil dikirim dan sedang menunggu verifikasi admin.');
    }
}
