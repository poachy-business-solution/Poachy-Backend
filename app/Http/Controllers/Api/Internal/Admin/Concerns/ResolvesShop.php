<?php

namespace App\Http\Controllers\Api\Internal\Admin\Concerns;

use App\Http\Responses\ApiResponse;
use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;

trait ResolvesShop
{
    /** A shop by tenant id or POA account number; 404 otherwise. */
    protected function shop(string $idOrAccount): Tenant
    {
        $tenant = Tenant::query()->whereKey($idOrAccount)
            ->orWhere('mpesa_paybill_account', strtoupper($idOrAccount))
            ->first();

        if ($tenant === null) {
            throw new HttpResponseException(ApiResponse::notFound('Shop not found.'));
        }

        return $tenant;
    }
}
