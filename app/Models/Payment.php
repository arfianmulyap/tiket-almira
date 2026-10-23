<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A manual payment submission with its uploaded proof (PRD FR-06).
 *
 * Nothing is mass assignable. Every attribute is derived server-side by
 * PaymentProofService: the amount is copied from the order's price snapshot,
 * the stored path comes from the filesystem, and submitted_at is server time.
 * Accepting any of these from request input would let a participant claim an
 * amount or a submission time of their choosing.
 *
 * This record carries no status column. Whether a payment is pending,
 * approved, or rejected is answered by the order's status, which is the single
 * value the quota count, the admin queue, and ticket issuance already read. A
 * second status here would be one more thing to keep in sync and one more way
 * for the two to disagree.
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Original filename is not preserved, so build a safe one for download.
     *
     * The participant's filename is never stored: it is attacker-controlled
     * text that would end up in a Content-Disposition header. Deriving the
     * name from the order reference instead gives admins something meaningful
     * and keeps the header free of untrusted input.
     */
    public function downloadFilename(): string
    {
        $extension = pathinfo($this->proof_path, PATHINFO_EXTENSION);

        return 'bukti-'.$this->order->order_reference.($extension ? '.'.$extension : '');
    }
}
