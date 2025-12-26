<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\CustomerGroup;
use Illuminate\Support\Facades\Auth;

class CustomerGroupObserver
{
    /**
     * Handle the CustomerGroup "created" event.
     */
    public function created(CustomerGroup $customerGroup): void {}

    /**
     * Handle the CustomerGroup "updated" event.
     */
    public function updated(CustomerGroup $customerGroup): void {}

    /**
     * Handle the CustomerGroup "deleted" event.
     */
    public function deleted(CustomerGroup $customerGroup): void {}
}
