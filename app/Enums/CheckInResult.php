<?php

namespace App\Enums;

/**
 * Outcome of one scan attempt (PRD FR-10).
 *
 * Every branch the scanner can take is named here so the operator-facing
 * wording, the colour, and the HTTP status code have one definition shared by
 * the JSON endpoint and the server-rendered fallback. Without this, the two
 * response paths would drift and the gate screen could show "valid" for a
 * request the server actually refused.
 *
 * Note what is deliberately absent: there is no case that distinguishes
 * "token does not exist" from "token is malformed". Telling a scanner which
 * of the two it hit would turn the endpoint into an oracle for probing valid
 * tokens, so both collapse into InvalidToken.
 */
enum CheckInResult: string
{
    case Success = 'SUCCESS';
    case AlreadyCheckedIn = 'ALREADY_CHECKED_IN';
    case InvalidToken = 'INVALID_TOKEN';
    case TicketVoid = 'TICKET_VOID';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Check-in berhasil',
            self::AlreadyCheckedIn => 'Tiket sudah digunakan',
            self::InvalidToken => 'QR tidak valid',
            self::TicketVoid => 'Tiket dibatalkan',
        };
    }

    /**
     * Short instruction for the officer at the gate.
     *
     * Phrased as what to do next, not as a description of the data: the
     * person reading this is standing in front of a queue.
     */
    public function description(): string
    {
        return match ($this) {
            self::Success => 'Peserta dipersilakan masuk.',
            self::AlreadyCheckedIn => 'Tiket ini sudah pernah dipakai check-in. Jangan izinkan masuk sebelum diperiksa admin.',
            self::InvalidToken => 'QR tidak dikenali. Pastikan peserta memakai e-ticket resmi dari Almira Tiket Event.',
            self::TicketVoid => 'Tiket ini dinonaktifkan. Arahkan peserta ke admin.',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::Success;
    }

    /**
     * Alert variant used to render this outcome.
     *
     * A refusal is never merely "info": at a gate the difference between
     * admitted and refused has to be unmissable at arm's length.
     */
    public function alertVariant(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::AlreadyCheckedIn => 'warning',
            self::InvalidToken, self::TicketVoid => 'error',
        };
    }

    /**
     * HTTP status for the JSON endpoint.
     *
     * A refused scan is a well-formed request that the server declined, so it
     * answers 422 rather than 200. That way a client cannot treat "refused"
     * as "admitted" just by checking `response.ok`.
     */
    public function httpStatus(): int
    {
        return $this->isSuccessful() ? 200 : 422;
    }
}
