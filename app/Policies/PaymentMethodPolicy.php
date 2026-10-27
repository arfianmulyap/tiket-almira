<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Authorisation for payment settings (Phase 12, extended in Phase 12B).
 *
 * Three different questions, deliberately answered by three different rules:
 *
 *  - Reading the catalog follows the grantable `payment_methods.view`.
 *  - Creating, editing details, toggling activation (global or per event), and
 *    deleting each follow their own grantable permission, so a role can be
 *    trusted with operational work without also being able to redirect money or
 *    erase a method.
 *  - Publishing placeholder details as official — clearing `is_dummy` — is a
 *    Super Admin decision, checked against the protected role rather than a
 *    permission that could be granted to a custom role by mistake. Until it is
 *    cleared, the method is hidden in production, which is what keeps an
 *    unconfirmed account number from ever reaching a participant.
 */
class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsView);
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsCreate);
    }

    public function updateDetails(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsUpdate);
    }

    /**
     * Global activation and per-event activation.
     *
     * One ability for both, because they are the same operational decision at
     * two scopes: whether a method is offered for new orders. The per-event
     * screen is still separate from the global toggle in the audit trail, so
     * "who switched this off for that event" stays answerable.
     */
    public function toggle(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsToggle);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsDelete);
    }

    /**
     * Whether this admin may mark a method's details as official (clear
     * `is_dummy`).
     *
     * Super Admin only: the flag is the last thing standing between an
     * unconfirmed example account number and a participant being told to
     * transfer into it (PRD section 15 questions 1 and 2).
     */
    public function publishOfficialDetails(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->isSuperAdmin();
    }
}
