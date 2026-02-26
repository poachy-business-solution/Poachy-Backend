<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerAddress;
use App\Services\Central\Marketplace\DeliveryFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryFeeController extends Controller
{
    public function __construct(
        private readonly DeliveryFeeService $deliveryFeeService,
    ) {}

    /**
     * Preview available delivery methods and fees for a given address and tenant.
     *
     * POST /api/v1/central/marketplace/delivery/preview
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id'           => ['required', 'string', 'exists:central.tenants,id'],
            'delivery_address_id' => ['required', 'integer', 'exists:central.customer_addresses,id'],
            'subtotal'            => ['nullable', 'numeric', 'min:0'],
        ]);

        $address  = CustomerAddress::on('central')->find($request->delivery_address_id);
        $subtotal = (float) ($request->subtotal ?? 0);

        $availableMethods = $this->deliveryFeeService->getAvailableMethodsForAddress(
            $request->tenant_id,
            $address,
            $subtotal,
        );

        if (empty($availableMethods)) {
            return ApiResponse::error(
                'Delivery is not available to your address from this merchant.',
                null,
                400,
            );
        }

        return ApiResponse::success('Delivery fees calculated', [
            'address' => [
                'id'     => $address->id,
                'city'   => $address->city,
                'county' => $address->county,
            ],
            'available_methods' => $availableMethods,
        ]);
    }
}
