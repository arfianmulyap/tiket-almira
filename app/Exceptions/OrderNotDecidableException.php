<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment decision is attempted on an order that is no longer
 * awaiting verification.
 *
 * Raised from inside the locked transaction, which is the only place where
 * the answer is authoritative: two admins may open the same order and both
 * click approve, and the second one must fail rather than overwrite the
 * first decision (PRD FR-07).
 */
class OrderNotDecidableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Pesanan ini sudah diputuskan atau belum siap diverifikasi.');
    }
}
