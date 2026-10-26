<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Order;
use App\Models\User;

/**
 * Authorisation for admin order and payment verification actions.
 *
 * The admin panel is already behind the `auth` and `admin` middleware, but
 * every ability is still checked per action so authorisation never depends on
 * route configuration alone. Each ability maps to a permission from the role
 * held by the admin (Phase 10).
 */
class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(AdminPermission::OrdersView);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->hasPermission(AdminPermission::OrdersView);
    }

    /**
     * Whether the admin may approve or reject this order's payment.
     *
     * A permission is not sufficient: the order must also still be awaiting a
     * decision. Encoding the state here means the UI, the form requests, and
     * the controller all refuse a second decision through one rule, and the
     * service re-checks it under a row lock for concurrent requests
     * (PRD FR-07).
     */
    public function decide(User $user, Order $order): bool
    {
        return $user->hasPermission(AdminPermission::PaymentVerify) && $order->awaitsDecision();
    }

    /**
     * Whether the admin may read this order's payment proof.
     *
     * Proofs are personal data held on a private disk (PRD FR-06), so access
     * is an explicit permission rather than an implicit consequence of being
     * able to see the order.
     */
    public function viewPaymentProof(User $user, Order $order): bool
    {
        return $user->hasPermission(AdminPermission::PaymentProofView);
    }

    /**
     * Whether the admin may view the issued ticket for this order.
     *
     * The ticket is shown on the order detail page, which is why this follows
     * the order permission; there is no separate admin ticket screen yet.
     */
    public function viewTicket(User $user, Order $order): bool
    {
        return $user->hasPermission(AdminPermission::OrdersView);
    }

    /**
     * Whether the admin may email this order's e-ticket again (Phase 13).
     *
     * A permission is necessary but not sufficient: there has to be a ticket to
     * send. Resending reuses the existing ticket and QR token, so this ability
     * can never issue a second ticket — that is TicketIssuanceService's job and
     * it is deliberately not reachable from this action.
     */
    public function resendTicket(User $user, Order $order): bool
    {
        return $user->hasPermission(AdminPermission::TicketsResend)
            && $order->ticket()->exists();
    }
}
