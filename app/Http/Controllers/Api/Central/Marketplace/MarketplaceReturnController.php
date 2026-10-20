<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\Returns\DisputeReturnRequest;
use App\Http\Requests\Central\Marketplace\Returns\OpenReturnRequest;
use App\Http\Resources\Central\Marketplace\ReturnCaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceReturnCase;
use App\Services\Central\Refunds\ReturnCaseService;
use Illuminate\Http\JsonResponse;

/**
 * The buyer's returns: ask, follow, cancel, dispute. Always the signed-in buyer's own orders.
 */
class MarketplaceReturnController extends Controller
{
    public function __construct(private readonly ReturnCaseService $returns) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/orders/{id}/returns",
     *     summary="Ask to return items from a collected/delivered order (multipart; up to 4 photos)",
     *     description="Within the shop's return window (0/3/7 days after handover); food, hygiene goods and airtime are never returnable. Shop at fault (faulty, wrong_item, not_as_described, damaged): items + delivery fee (if all returned). changed_mind: items less the buyer's share of the payment fee.",
     *     operationId="marketplaceOpenReturn",
     *     tags={"Central - Marketplace - Returns"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *         required={"items", "reason_code"},
     *         @OA\Property(property="items", type="array", @OA\Items(type="object", @OA\Property(property="order_item_id", type="integer"), @OA\Property(property="quantity", type="number"))),
     *         @OA\Property(property="reason_code", type="string", enum={"changed_mind", "faulty", "wrong_item", "not_as_described", "damaged"}),
     *         @OA\Property(property="description", type="string", maxLength=1000),
     *         @OA\Property(property="photos[]", type="array", @OA\Items(type="string", format="binary"))
     *     ))),
     *
     *     @OA\Response(response=201, description="Return opened"),
     *     @OA\Response(response=404, description="Not this buyer's order"),
     *     @OA\Response(response=422, description="Not returnable (window, category, open case) or invalid items")
     * )
     */
    public function store(OpenReturnRequest $request, int $id): JsonResponse
    {
        $order = $this->ownOrder($id);
        $case = $this->returns->open($order, $request->validated('items'), $request->validated('reason_code'), $request->validated('description'), $request->file('photos', []));

        return ApiResponse::created('Return requested — bring the items back to the shop', new ReturnCaseResource($case->load('order', 'refunds')));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/returns",
     *     summary="The buyer's returns, newest first",
     *     operationId="marketplaceListReturns",
     *     tags={"Central - Marketplace - Returns"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="Returns")
     * )
     */
    public function index(): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $cases = MarketplaceReturnCase::query()->where('customer_id', $customer->id)->with('order', 'refunds')->latest('id')->limit(100)->get();

        return ApiResponse::success('Returns', ReturnCaseResource::collection($cases));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/returns/{reference}",
     *     summary="One of the buyer's returns",
     *     operationId="marketplaceShowReturn",
     *     tags={"Central - Marketplace - Returns"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Return"),
     *     @OA\Response(response=404, description="Not this buyer's return")
     * )
     */
    public function show(string $reference): JsonResponse
    {
        return ApiResponse::success('Return', new ReturnCaseResource($this->ownCase($reference)->load('order', 'refunds')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/returns/{reference}/dispute",
     *     summary="Dispute the shop's rejection or partial refund (within 48 h; multipart, up to 4 photos)",
     *     operationId="marketplaceDisputeReturn",
     *     tags={"Central - Marketplace - Returns"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Disputed — Poachy decides"),
     *     @OA\Response(response=422, description="Nothing to dispute or too late")
     * )
     */
    public function dispute(DisputeReturnRequest $request, string $reference): JsonResponse
    {
        $case = $this->returns->dispute($this->ownCase($reference), $request->validated('reason'), $request->file('photos', []));

        return ApiResponse::success('Dispute sent to Poachy', new ReturnCaseResource($case->load('order', 'refunds')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/returns/{reference}/cancel",
     *     summary="Withdraw a return the shop has not received yet",
     *     operationId="marketplaceCancelReturn",
     *     tags={"Central - Marketplace - Returns"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Cancelled"),
     *     @OA\Response(response=422, description="Already received")
     * )
     */
    public function cancel(string $reference): JsonResponse
    {
        return ApiResponse::success('Return cancelled', new ReturnCaseResource($this->returns->cancel($this->ownCase($reference))->load('order', 'refunds')));
    }

    private function ownOrder(int $id): MarketplaceOrder
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        return MarketplaceOrder::on('central')->where('customer_id', $customer->id)->findOrFail($id);
    }

    private function ownCase(string $reference): MarketplaceReturnCase
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        return MarketplaceReturnCase::query()->where('customer_id', $customer->id)->where('reference', $reference)->firstOrFail();
    }
}
