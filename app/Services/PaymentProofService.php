<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\OrderStatus;
use App\Exceptions\PaymentProofNotAcceptedException;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentMethod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Accepts a payment proof and moves the order into the verification queue
 * (PRD FR-06, FR-08).
 *
 * The deadline check is the whole point of this class. A participant can sit
 * on the upload form for an hour, so the status shown when the page rendered
 * proves nothing. The order is therefore re-read under a row lock and the
 * deadline re-evaluated against server time before anything is written. The
 * clock is checked as well as the status, so a proof arriving at minute 61 is
 * refused even when the expiry sweep has not run yet.
 */
class PaymentProofService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Store the proof, record the payment, and flip the order to
     * PENDING_VERIFICATION.
     *
     * The file is written to disk before the transaction opens, because a
     * filesystem write cannot be rolled back by the database and holding a
     * row lock during an upload would block the row for the duration of the
     * I/O. If the transaction then fails, the orphaned file is deleted in the
     * catch block: a stray file is recoverable, a half-written order is not.
     *
     * @throws PaymentProofNotAcceptedException
     */
    public function submit(
        Order $order,
        PaymentMethod $method,
        UploadedFile $proof,
    ): Payment {
        // Cheap pre-check purely to avoid storing a file for an order that is
        // already dead. It is NOT the authoritative check; the one inside the
        // lock below is.
        if (! $order->acceptsPaymentProof()) {
            throw PaymentProofNotAcceptedException::make();
        }

        $path = $this->storeProof($proof);

        try {
            return DB::transaction(
                fn (): Payment => $this->record($order, $method, $proof, $path)
            );
        } catch (Throwable $exception) {
            // The database rolled back; the file did not. Remove it so a
            // refused or failed submission leaves nothing behind.
            $this->deleteProof($path);

            throw $exception;
        }
    }

    /**
     * Create the payment and advance the order, inside the caller's
     * transaction and under a row lock.
     *
     * @throws PaymentProofNotAcceptedException
     */
    private function record(
        Order $order,
        PaymentMethod $method,
        UploadedFile $proof,
        string $path,
    ): Payment {
        $locked = Order::query()
            ->whereKey($order->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        // Authoritative check: under the lock and against server time. Two
        // concurrent uploads for the same order serialise here. The first
        // commits and flips the status; the second re-reads it, finds it is
        // no longer PENDING_PAYMENT, and is refused. The unique index on
        // payments.order_id is the backstop if this ever fails.
        if (! $locked->acceptsPaymentProof()) {
            throw PaymentProofNotAcceptedException::make();
        }

        $payment = new Payment;

        $payment->order_id = $locked->getKey();
        $payment->method = $method->key;

        // Snapshot the label as shown, so renaming a method in config does
        // not rewrite what the admin reviews.
        $payment->method_label = $method->label;

        // From the order's own snapshot, never from request input: the amount
        // due is not the participant's to declare.
        $payment->amount = $locked->ticket_price_snapshot;

        $payment->proof_path = $path;

        // Server-side detection, not the browser's Content-Type header.
        $payment->proof_mime_type = $proof->getMimeType() ?? 'application/octet-stream';

        // Server time. This is the value the deadline rule depends on, so it
        // must not be influenceable by the client.
        $payment->submitted_at = now();

        $payment->save();

        // The quota slot is already held and simply stays held:
        // PENDING_VERIFICATION holds unconditionally, so the order keeps its
        // slot past minute 60 (PRD FR-08). Nothing is incremented here, which
        // is why the expiry sweep can never double-release it.
        $locked->status = OrderStatus::PendingVerification;
        $locked->save();

        // No actor: the participant is not an authenticated user, and
        // inventing one would make the trail claim an admin acted.
        $this->audit->record($locked, AuditAction::PaymentProofSubmitted, null, [
            'order_reference' => $locked->order_reference,
            'method' => $method->key,
            'amount' => $payment->amount,
        ]);

        // Refresh the caller's instance so the response it renders next does
        // not still report PENDING_PAYMENT.
        $order->setRawAttributes($locked->getAttributes());
        $order->syncOriginal();

        return $payment;
    }

    /**
     * Write the proof to the private disk under a random filename.
     *
     * The participant's original filename is discarded rather than sanitised.
     * It is attacker-controlled and would otherwise influence the path on
     * disk; a random name removes the question entirely and makes proofs
     * unguessable even to someone who reaches the directory.
     *
     * The extension comes from the server-side guess, not the submitted name,
     * so "receipt.php.jpg" cannot land on disk as a .php file.
     */
    private function storeProof(UploadedFile $proof): string
    {
        $extension = $proof->extension() ?: 'bin';

        $path = $proof->storeAs(
            $this->directory(),
            Str::random(40).'.'.$extension,
            $this->disk()
        );

        if ($path === false) {
            throw PaymentProofNotAcceptedException::storageFailed();
        }

        return $path;
    }

    /**
     * Delete a stored proof, tolerating a path that is already gone.
     */
    public function deleteProof(string $path): void
    {
        $disk = Storage::disk($this->disk());

        if ($disk->exists($path)) {
            $disk->delete($path);
        }
    }

    /**
     * Disk holding payment proofs.
     *
     * Read from config so the service, the download controller, and the tests
     * all share one definition.
     */
    public function disk(): string
    {
        return (string) config('payments.proof.disk', 'local');
    }

    private function directory(): string
    {
        return (string) config('payments.proof.directory', 'payment-proofs');
    }
}
