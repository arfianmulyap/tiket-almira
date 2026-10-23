<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when no quota slot is left at the moment the order is committed.
 *
 * This is raised from inside the locked transaction, which is the only
 * place where the answer is authoritative (PRD business rule 15).
 */
class QuotaUnavailableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Kuota tiket untuk event ini sudah habis.');
    }
}
