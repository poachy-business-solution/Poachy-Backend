<?php

namespace App\Policies\Tenant;

use App\Models\Tenant\SupplierPayment;
use App\Models\Tenant\User;

class SupplierPaymentPolicy
{
    /**
     * Determine whether the user can view any supplier payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view-supplier-payments');
    }

    /**
     * Determine whether the user can view the supplier payment.
     */
    public function view(User $user, SupplierPayment $payment): bool
    {
        return $user->can('view-supplier-payments');
    }

    /**
     * Determine whether the user can create supplier payments.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-supplier-payments');
    }

    // No update or delete methods since payments are final once recorded
}
