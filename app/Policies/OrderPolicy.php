<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * Authorisation for admin order and payment verification actions.
 *
 * The admin panel is already behind the `auth` and `admin` middleware, but
 * every ability is still checked per action so authorisation never depends on
 * route configuration alone. MVP assumes a single admin role (PRD section 4).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether the admin may approve or reject this order's payment.
     *
     * Being an admin is not sufficient: the order must also still be awaiting
     * a decision. Encoding the state here means the UI, the form requests,
     * and the controller all refuse a second decision through one rule, and
     * the service re-checks it under a row lock for concurrent requests
     * (PRD FR-07).
     */
    public function decide(User $user, Order $order): bool
    {
        return $user->isAdmin() && $order->awaitsDecision();
    }

    /**
     * Whether the admin may read this order's payment proof.
     *
     * Proofs are personal data held on a private disk (PRD FR-06), so access
     * is an explicit ability rather than an implicit consequence of reaching
     * the admin panel. Kept separate from view() so the two can diverge if a
     * finance role is introduced later (PRD section 4 leaves that open).
     */
    public function viewPaymentProof(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether the admin may view the issued ticket for this order.
     */
    public function viewTicket(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}
