<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment proof cannot be accepted for an order.
 *
 * Raised from inside the locked transaction in PaymentProofService, which is
 * the only place where the answer is authoritative: the order may have expired
 * or already received a proof between rendering the form and submitting it
 * (PRD FR-06, FR-08).
 */
class PaymentProofNotAcceptedException extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Bukti pembayaran tidak dapat diterima. Pesanan ini sudah '
            .'kedaluwarsa atau buktinya sudah pernah dikirim.'
        );
    }

    /**
     * The file could not be written to the private disk.
     *
     * Kept distinct from make(): this is an infrastructure fault, not a
     * business rule, and the participant should be told to retry rather than
     * that their order is dead.
     */
    public static function storageFailed(): self
    {
        return new self(
            'Bukti pembayaran gagal disimpan. Silakan coba unggah kembali.'
        );
    }
}
