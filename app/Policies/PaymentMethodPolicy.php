<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Authorisation for payment settings (Phase 12).
 *
 * Two different questions, deliberately answered by two different rules:
 *
 *  - Toggling a method active or inactive is ordinary operational work and
 *    follows the grantable `payment-methods.manage` permission.
 *  - Editing the account, VA, and QRIS details decides where participant
 *    money is sent. Phase 12 restricts that to the Super Admin role, so it is
 *    checked against the protected role rather than a permission that could be
 *    granted to a custom role by mistake.
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

    public function toggle(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->hasPermission(AdminPermission::PaymentMethodsManage);
    }

    public function updateDetails(User $user, PaymentMethod $paymentMethod): bool
    {
        return $user->isSuperAdmin();
    }
}
