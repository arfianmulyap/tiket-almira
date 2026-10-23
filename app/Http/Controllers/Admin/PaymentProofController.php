<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\PaymentProofService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentProofController extends Controller
{
    public function __construct(private readonly PaymentProofService $proofs) {}

    /**
     * Stream the payment proof to an authorised admin (PRD FR-06).
     *
     * The file lives on the private disk and has no public URL, so this is
     * the only way to read it. Authorisation is checked here rather than
     * relying on the admin middleware alone, so the rule survives any future
     * change to the route group.
     *
     * The response is streamed inline so it can be displayed in the review
     * page, with headers that stop the browser from sniffing a different
     * content type out of the bytes.
     */
    public function show(Order $order): StreamedResponse
    {
        $this->authorize('viewPaymentProof', $order);

        $payment = $order->payment;

        if ($payment === null) {
            throw new NotFoundHttpException;
        }

        $disk = Storage::disk($this->proofs->disk());

        if (! $disk->exists($payment->proof_path)) {
            // The database row survived but the file is gone. A 404 is
            // honest here; silently rendering a broken image would hide a
            // real storage problem from the admin.
            throw new NotFoundHttpException;
        }

        return $disk->response(
            $payment->proof_path,
            $payment->downloadFilename(),
            [
                // Trust the type detected at upload time, never the bytes.
                'Content-Type' => $payment->proof_mime_type,
                // Stops a crafted file from being interpreted as HTML or a
                // script in the admin's browser session.
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
                // Proofs are personal data; keep them out of shared caches.
                'Cache-Control' => 'private, no-store, max-age=0',
            ]
        );
    }
}
