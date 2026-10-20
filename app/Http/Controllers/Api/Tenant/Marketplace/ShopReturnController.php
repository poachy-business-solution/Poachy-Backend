<?php

namespace App\Http\Controllers\Api\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\InspectReturnRequest;
use App\Http\Resources\Central\Marketplace\ReturnCaseResource;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceOrderItem;
use App\Models\MarketplaceReturnCase;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\Product;
use App\Services\Central\Refunds\ReturnCaseService;
use App\Services\Tenant\Inventory\InventoryMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Returns at the shop (app → Marketplace → Returns): this shop's cases only. "Received" starts
 * the 48 h inspection; accept / partial puts the items back in stock (a return movement on the
 * POS inventory) and the refund follows; reject waits 48 h for the buyer.
 */
class ShopReturnController extends Controller
{
    public function __construct(
        private readonly ReturnCaseService $returns,
        private readonly InventoryMovementService $movements,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/returns",
     *     summary="This shop's returns (open first)",
     *     operationId="tenantMarketplaceReturns",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="open", in="query", required=false, @OA\Schema(type="integer", enum={0, 1})),
     *
     *     @OA\Response(response=200, description="Returns + counts"),
     *     @OA\Response(response=403, description="No view-marketplace-sales")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) tenant()->id;
        $query = MarketplaceReturnCase::query()->where('tenant_id', $tenantId)->with('order', 'refunds');
        if ($request->boolean('open')) {
            $query->whereIn('status', MarketplaceReturnCase::OPEN);
        }

        return ApiResponse::success('Returns', [
            'returns' => ReturnCaseResource::collection($query->latest('id')->limit(100)->get()),
            'counts' => [
                'to_receive' => MarketplaceReturnCase::query()->where('tenant_id', $tenantId)->where('status', MarketplaceReturnCase::REQUESTED)->count(),
                'to_inspect' => MarketplaceReturnCase::query()->where('tenant_id', $tenantId)->where('status', MarketplaceReturnCase::RECEIVED)->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/returns/{reference}",
     *     summary="One of this shop's returns",
     *     operationId="tenantMarketplaceReturn",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Return"),
     *     @OA\Response(response=404, description="Not this shop's return")
     * )
     */
    public function show(string $reference): JsonResponse
    {
        return ApiResponse::success('Return', new ReturnCaseResource($this->own($reference)->load('order', 'refunds')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/marketplace/returns/{reference}/received",
     *     summary="The buyer brought the items back — the 48 h inspection starts",
     *     operationId="tenantMarketplaceReturnReceived",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Received"),
     *     @OA\Response(response=422, description="Not waiting for the items")
     * )
     */
    public function received(string $reference): JsonResponse
    {
        return ApiResponse::success('Items received', new ReturnCaseResource($this->returns->received($this->own($reference))->load('order', 'refunds')));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/marketplace/returns/{reference}/inspect",
     *     summary="The shop's inspection: accept (refund now, items back in stock), partial or reject (buyer may dispute within 48 h)",
     *     operationId="tenantMarketplaceReturnInspect",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *         required={"decision"},
     *
     *         @OA\Property(property="decision", type="string", enum={"accept", "partial", "reject"}),
     *         @OA\Property(property="refund_amount", type="number", description="For partial"),
     *         @OA\Property(property="reason", type="string", description="Required for partial and reject"),
     *         @OA\Property(property="photos[]", type="array", @OA\Items(type="string", format="binary"))
     *     ))),
     *
     *     @OA\Response(response=200, description="Inspected"),
     *     @OA\Response(response=403, description="No manage-marketplace-sales"),
     *     @OA\Response(response=422, description="Not received yet, already decided, or invalid amount")
     * )
     */
    public function inspect(InspectReturnRequest $request, string $reference): JsonResponse
    {
        $case = $this->own($reference);
        $decision = (string) $request->validated('decision');
        $amount = $request->validated('refund_amount');
        $case = $this->returns->inspect($case, $decision, $amount === null ? null : (float) $amount, $request->validated('reason'), $request->file('photos', []));

        if ($decision !== 'reject' && $case->restocked_at === null) {
            $this->restock($case);
            $case->update(['restocked_at' => now()]);
        }

        return ApiResponse::success('Inspection saved', new ReturnCaseResource($case->fresh()->load('order', 'refunds')));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/marketplace/returns/{reference}/photos/{index}",
     *     summary="One of the buyer's evidence photos (0-based)",
     *     operationId="tenantMarketplaceReturnPhoto",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="index", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="The image"),
     *     @OA\Response(response=404, description="No such photo")
     * )
     */
    public function photo(string $reference, int $index): StreamedResponse
    {
        $path = ($this->own($reference)->buyer_evidence ?? [])[$index] ?? null;
        abort_if($path === null || ! Storage::disk('returns')->exists($path), 404);

        return Storage::disk('returns')->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    private function own(string $reference): MarketplaceReturnCase
    {
        return MarketplaceReturnCase::query()->where('tenant_id', (string) tenant()->id)->where('reference', $reference)->firstOrFail();
    }

    /** The returned items go back on the shelf of the branch that sold them. */
    private function restock(MarketplaceReturnCase $case): void
    {
        $sale = MarketplaceSale::query()->where('central_order_id', $case->order_id)->first();
        if ($sale === null) {
            return;
        }
        $orderItems = MarketplaceOrderItem::on('central')->whereIn('id', collect($case->items)->pluck('order_item_id'))->get()->keyBy('id');

        foreach ($case->items as $line) {
            $item = $orderItems->get($line['order_item_id']);
            if ($item === null || $item->tenant_product_id === null || $item->tenant_bundle_id !== null) {
                continue;
            }
            $this->movements->recordReturn([
                'store_id' => $sale->store_id,
                'product_id' => $item->tenant_product_id,
                'variant_id' => $item->tenant_variant_id,
                'uom_id' => Product::query()->whereKey($item->tenant_product_id)->value('base_uom_id'),
                'quantity' => (float) $line['quantity'],
                'reference_type' => MarketplaceSale::class,
                'reference_id' => $sale->id,
                'notes' => "Online return {$case->reference} — {$sale->sale_number}",
            ]);
        }
    }
}
