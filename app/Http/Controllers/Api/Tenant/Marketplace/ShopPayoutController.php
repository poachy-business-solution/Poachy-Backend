<?php

namespace App\Http\Controllers\Api\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\ListShopPayoutsRequest;
use App\Http\Resources\Tenant\Marketplace\ShopPayoutResource;
use App\Http\Responses\ApiResponse;
use App\Models\BusinessDetail;
use App\Services\Central\Payouts\PayoutStatementService;
use Illuminate\Http\JsonResponse;

/**
 * The shop's marketplace money: balance, payouts and payout statements (this shop only).
 */
class ShopPayoutController extends Controller
{
    public function __construct(private readonly PayoutStatementService $statements) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/payouts",
     *     summary="The shop's marketplace balance and payouts",
     *     description="Owners/managers (view-financial-reports). held = handed over less than 24 h ago; available = goes out in the next nightly payout (02:00 Nairobi) to the masked payout number.",
     *     operationId="tenantMarketplacePayouts",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", maximum=100)),
     *
     *     @OA\Response(response=200, description="Balance + payouts", @OA\JsonContent(
     *
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="balance", type="object",
     *                 @OA\Property(property="held", type="number"), @OA\Property(property="available", type="number"),
     *                 @OA\Property(property="next_payout_at", type="string", format="date-time"), @OA\Property(property="payout_phone", type="string", nullable=true)),
     *             @OA\Property(property="payouts", type="array", @OA\Items(type="object",
     *                 @OA\Property(property="id", type="integer"), @OA\Property(property="reference", type="string"), @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="status", type="string", enum={"processing", "paid", "failed", "needs_check", "failed_final"}),
     *                 @OA\Property(property="mpesa_receipt", type="string", nullable=true), @OA\Property(property="paid_at", type="string", nullable=true))),
     *             @OA\Property(property="pagination", type="object")
     *         )
     *     )),
     *
     *     @OA\Response(response=403, description="Cashier or other role without view-financial-reports")
     * )
     */
    public function index(ListShopPayoutsRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->id;
        $details = BusinessDetail::query()->where('tenant_id', $tenantId)->first(['payout_phone', 'business_phone']);
        $page = $this->statements->payouts($tenantId, (int) ($request->validated('per_page') ?? 20));

        return ApiResponse::success('Payouts', [
            'balance' => $this->statements->balance($tenantId, $details?->payout_phone ?: $details?->business_phone),
            'payouts' => ShopPayoutResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/payouts/{id}",
     *     summary="A payout statement: each order (gross, fee, commission, net), clawbacks and adjustments",
     *     operationId="tenantMarketplacePayoutStatement",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Statement"),
     *     @OA\Response(response=404, description="Not this shop's payout")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $payout = $this->statements->find((string) tenant()->id, $id);

        return $payout === null
            ? ApiResponse::notFound('Payout not found')
            : ApiResponse::success('Payout statement', new ShopPayoutResource($payout));
    }
}
