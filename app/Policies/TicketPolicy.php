<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Ticket;
use App\Models\User;

/**
 * Authorisation for ticket and check-in actions (PRD FR-10).
 *
 * The admin panel already sits behind the `auth` and `admin` middleware, but
 * every ability is still checked per action so authorisation never depends on
 * route configuration alone. Both abilities map to the same check-in
 * permission (Phase 10) because the scanner screen and the admission it
 * performs are one operational capability; the methods stay separate so a
 * read-only gate log can be split out later without touching the routes.
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
        return $user->hasPermission(AdminPermission::CheckInPerform);
    }

    /**
     * Whether the user may view the gate log.
     */
    public function viewCheckIns(User $user): bool
    {
        return $user->hasPermission(AdminPermission::CheckInPerform);
    }

    /**
     * Whether a recorded check-in may be reversed.
     *
     * Always false. PRD section 15 question 14 ("bolehkah admin membatalkan
     * atau mereset check-in?") is unanswered, and reversing an admission is
     * exactly the kind of destructive action that must not be invented: it
     * would let one ticket admit two people. The ability exists so the
     * decision has an obvious home once it is made. No permission can enable
     * it, including Super Admin, because this is a business decision rather
     * than an access decision.
     */
    public function resetCheckIn(User $user, Ticket $ticket): bool
    {
        return false;
    }
}
