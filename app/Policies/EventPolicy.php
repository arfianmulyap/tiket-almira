<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

/**
 * Authorisation for event management.
 *
 * The admin panel is already gated by the `auth` and `admin` middleware, but
 * the policy is still enforced per action so authorisation does not depend on
 * route configuration alone. MVP assumes a single admin role (PRD section 4),
 * so every ability maps to the same check for now.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Event $event): bool
    {
        return $user->isAdmin();
    }

    /**
     * Deleting an event is not part of the Phase 03 scope: orders and tickets
     * will reference events from Phase 04 onward, and removing an event with
     * existing transactions would break them. Admins close events instead.
     */
    public function delete(User $user, Event $event): bool
    {
        return false;
    }
}
