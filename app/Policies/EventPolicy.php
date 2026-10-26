<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Event;
use App\Models\User;

/**
 * Authorisation for event management.
 *
 * The admin panel is already gated by the `auth` and `admin` middleware, but
 * the policy is still enforced per action so authorisation does not depend on
 * route configuration alone. Each ability maps to a permission from the role
 * held by the admin (Phase 10), so revoking a permission removes the action as
 * well as the menu entry.
 */
class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(AdminPermission::EventsView);
    }

    public function view(User $user, Event $event): bool
    {
        return $user->hasPermission(AdminPermission::EventsView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(AdminPermission::EventsCreate);
    }

    public function update(User $user, Event $event): bool
    {
        return $user->hasPermission(AdminPermission::EventsUpdate);
    }

    /**
     * Deleting an event is not part of the Phase 03 scope: orders and tickets
     * will reference events from Phase 04 onward, and removing an event with
     * existing transactions would break them. Admins close events instead.
     *
     * No permission can enable this: an unused permission would imply the
     * action is available.
     */
    public function delete(User $user, Event $event): bool
    {
        return false;
    }
}
