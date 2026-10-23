<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Authorisation for ticket and check-in actions (PRD FR-10).
 *
 * The admin panel already sits behind the `auth` and `admin` middleware, but
 * every ability is still checked per action so authorisation never depends on
 * route configuration alone. MVP assumes a single admin role (PRD section 4),
 * so each ability maps to the same check; they are kept separate because a
 * gate-officer role that may scan but not manage events is the most likely
 * next change (PRD section 4 leaves that open).
 */
class TicketPolicy
{
    /**
     * Whether the user may operate the check-in scanner.
     *
     * Class-level rather than per-ticket: the scanner is opened before any
     * ticket is known, so the gate screen has to be authorised on its own.
     */
    public function checkIn(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether the user may view the gate log.
     */
    public function viewCheckIns(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether a recorded check-in may be reversed.
     *
     * Always false. PRD section 15 question 14 ("bolehkah admin membatalkan
     * atau mereset check-in?") is unanswered, and reversing an admission is
     * exactly the kind of destructive action that must not be invented: it
     * would let one ticket admit two people. The ability exists so the
     * decision has an obvious home once it is made.
     */
    public function resetCheckIn(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
