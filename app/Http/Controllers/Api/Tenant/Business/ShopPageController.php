<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Helpers\PhoneNumber;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Business\UpdateShopPageRequest;
use App\Http\Resources\Tenant\Business\ShopPageResource;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Services\Central\Marketplace\MarketplaceProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The shop's public page as its owner sees it: link, badge, pin, WhatsApp, return window. */
class ShopPageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-details/shop-page",
     *     summary="The shop's public page settings (owner)",
     *     operationId="tenantShopPageShow",
     *     tags={"Tenant - Business"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="slug, public_url, is_verified, is_listed, name, logo_url, area, operating_hours, location, phone, whatsapp_number, whatsapp_link, return_window_days, return_policy"),
     *     @OA\Response(response=404, description="Business details not yet submitted")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $detail = $this->detail();

        if (! $detail) {
            return ApiResponse::notFound('Business details not yet submitted');
        }

        return ApiResponse::success('Shop page', new ShopPageResource($detail));
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/shop-page",
     *     summary="Edit the shop's public page (owner)",
     *     operationId="tenantShopPageUpdate",
     *     tags={"Tenant - Business"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(@OA\JsonContent(
     *
     *         @OA\Property(property="slug", type="string", example="mama-njeri-shop", description="Lowercase letters, numbers, hyphens; unique"),
     *         @OA\Property(property="whatsapp_number", type="string", nullable=true, example="0712345678"),
     *         @OA\Property(property="latitude", type="number", nullable=true, example=-1.2833),
     *         @OA\Property(property="longitude", type="number", nullable=true, example=36.8167),
     *         @OA\Property(property="return_window_days", type="integer", enum={0,3,7}, example=3),
     *         @OA\Property(property="payout_phone", type="string", nullable=true, example="0712345678", description="M-Pesa number for marketplace payouts; empty = business phone")
     *     )),
     *
     *     @OA\Response(response=200, description="The updated shop page"),
     *     @OA\Response(response=403, description="Only the owner edits the shop page"),
     *     @OA\Response(response=422, description="Validation error (slug taken, bad number, half a GPS pin)")
     * )
     */
    public function update(UpdateShopPageRequest $request): JsonResponse
    {
        $detail = $this->detail();

        if (! $detail) {
            return ApiResponse::notFound('Business details not yet submitted');
        }

        $data = $request->validated();

        if (array_key_exists('payout_phone', $data)) {
            $data['payout_phone'] = filled($data['payout_phone']) ? PhoneNumber::normalize($data['payout_phone']) : null;
        }

        if (array_key_exists('whatsapp_number', $data)) {
            $data['whatsapp_number'] = filled($data['whatsapp_number']) ? PhoneNumber::normalize($data['whatsapp_number']) : null;
        }

        DB::connection('central')->transaction(fn () => $detail->fill($data)->save());
        app(MarketplaceProductService::class)->flushCache(); // shop fields ride along on listings

        return ApiResponse::success('Shop page updated', new ShopPageResource($detail->fresh()));
    }

    private function detail(): ?BusinessDetail
    {
        return BusinessDetail::on('central')->where('tenant_id', tenant()->id)->first();
    }
}
