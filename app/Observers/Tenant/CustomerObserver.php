<?php

namespace App\Observers\Tenant;

use App\Helpers\PhoneNumberNormalizer;
use App\Models\Tenant\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CustomerObserver
{
    /**
     * Handle the Customer "creating" event.
     */
    public function creating(Customer $customer): void
    {
        // Generate customer number if not provided
        if (empty($customer->customer_number)) {
            $customer->customer_number = $this->generateCustomerNumber();
        }

        // Normalize phone number (safety layer - should already be normalized from request)
        if (!empty($customer->phone)) {
            $normalizedPhone = PhoneNumberNormalizer::normalize($customer->phone);

            if ($customer->phone !== $normalizedPhone) {
                $customer->phone = $normalizedPhone;
            }
        }

        // Set registered_at timestamp
        if (empty($customer->registered_at)) {
            $customer->registered_at = now();
        }

        // Initialize default values if not set
        $customer->loyalty_points = $customer->loyalty_points ?? 0;
        $customer->total_lifetime_purchases = $customer->total_lifetime_purchases ?? 0;
        $customer->total_visits = $customer->total_visits ?? 0;
        $customer->credit_limit = $customer->credit_limit ?? 0;
        $customer->current_debt = $customer->current_debt ?? 0;
        $customer->accepts_marketing = $customer->accepts_marketing ?? false;
    }

    /**
     * Handle the Customer "updating" event.
     */
    public function updating(Customer $customer): void
    {
        // Normalize phone number if it's being changed
        if ($customer->isDirty('phone') && !empty($customer->phone)) {
            $normalizedPhone = PhoneNumberNormalizer::normalize($customer->phone);

            if ($customer->phone !== $normalizedPhone) {
                $customer->phone = $normalizedPhone;
            }
        }
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void {}

    /**
     * Handle the Customer "updated" event.
     */
    public function updated(Customer $customer): void {}

    /**
     * Handle the Customer "deleted" event.
     */
    public function deleted(Customer $customer): void {}

    /**
     * Handle the Customer "restored" event.
     */
    public function restored(Customer $customer): void {}

    /**
     * Generate unique customer number
     */
    private function generateCustomerNumber(): string
    {
        $prefix = 'CUST-';
        $year = date('Y');

        // Get last customer number for this year
        $lastCustomer = Customer::withTrashed()
            ->where('customer_number', 'like', "{$prefix}{$year}-%")
            ->orderByDesc('id')
            ->first();

        if ($lastCustomer) {
            // Extract sequence number and increment
            $lastNumber = (int) substr($lastCustomer->customer_number, -6);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s%s-%06d', $prefix, $year, $newNumber);
    }
}
