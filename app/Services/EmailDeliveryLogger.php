<?php

namespace App\Services;

use App\Enums\EmailDeliveryStatus;
use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Records the outcome of transactional email delivery (Phase 13).
 *
 * Deliberately shaped like AuditLogger: the actor is passed in rather than read
 * from auth(), the row is written by the caller outside its database
 * transaction, and nothing here throws. A mail problem is data to read later,
 * never a reason to fail the operation that triggered the email — PRD FR-09
 * says a failed email must not cancel a valid ticket, and the same applies to
 * an admin invitation.
 */
class EmailDeliveryLogger
{
    /**
     * SMTP servers sometimes answer with a paragraph. The panel only needs the
     * first line or two, and a text column should not grow without a bound.
     */
    private const MAX_ERROR_LENGTH = 1000;

    /**
     * Append one attempt to the delivery trail.
     */
    public function record(
        Model $emailable,
        string $recipientEmail,
        EmailDeliveryStatus $status,
        ?User $actor = null,
        ?string $error = null,
    ): EmailDelivery {
        $delivery = new EmailDelivery;

        $delivery->emailable_type = $emailable->getMorphClass();
        $delivery->emailable_id = $emailable->getKey();
        $delivery->recipient_email = $recipientEmail;
        $delivery->status = $status;
        $delivery->sent_by = $actor?->getKey();
        $delivery->error_message = $error === null
            ? null
            : mb_substr($error, 0, self::MAX_ERROR_LENGTH);
        $delivery->created_at = now();

        $delivery->save();

        return $delivery;
    }
}
