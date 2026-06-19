<?php

namespace App\Helpers;

use App\Models\MarketplaceCustomer;
use Illuminate\Support\Facades\Auth;

class CustomerHelper
{
    /**
     * Centralized logic to fetch customer profile or throw a 404.
     */
    public static function getAuthenticatedCustomerOrFail(): MarketplaceCustomer
    {
        $customer = self::getAuthenticatedCustomer();

        if (!$customer) {
            abort(404, 'Customer profile not found');
        }

        return $customer;
    }

    /**
     * Fetch the customer record linked to the current central user.
     */
    public static function getAuthenticatedCustomer(): ?MarketplaceCustomer
    {
        $userId = Auth::guard('central')->id();

        if (!$userId) {
            return null;
        }

        return MarketplaceCustomer::on('central')
            ->where('user_id', $userId)
            ->first();
    }
}