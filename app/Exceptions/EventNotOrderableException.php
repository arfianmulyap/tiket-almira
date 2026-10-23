<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an order is attempted against an event that is not published.
 *
 * Normally the controller refuses this earlier, but the check is repeated
 * inside the locked transaction because an admin could close the event
 * between the page render and the form submit.
 */
class EventNotOrderableException extends RuntimeException
{
    public static function make(): self
    {
        return new self('Event ini tidak menerima pemesanan.');
    }
}
