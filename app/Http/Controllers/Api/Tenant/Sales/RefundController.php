<?php

namespace App\Http\Controllers\Api\Tenant\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Sales\InitiateExchangeRequest;
use App\Http\Requests\Tenant\Sales\InitiateRefundRequest;
use App\Http\Requests\Tenant\Sales\ListRefundsRequest;
use App\Http\Resources\Tenant\Sales\SaleRefundItemResource;
use App\Http\Resources\Tenant\Sales\SaleRefundResource;
use App\Http\Resources\Tenant\Sales\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Sale;
use App\Models\Tenant\SaleRefund;
use App\Models\Tenant\SaleRefundItem;
use App\Services\Tenant\Sales\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class RefundController extends Controller
{
    public function __construct(
        protected RefundService $refundService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales/{sale}/refundable-items",
     *     summary="Get refundable items for a sale",
     *     description="Returns each sale line item with the maximum quantity still available for refund, accounting for any previous partial refunds.",
     *     operationId="getRefundableItems",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sale", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Refundable items retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Refundable items retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="sale_id", type="integer", example=1),
     *                 @OA\Property(property="sale_number", type="string", example="INV-01-202501-000001"),
     *                 @OA\Property(property="can_refund", type="boolean", example=true),
     *                 @OA\Property(property="total_refunded", type="number", format="float", example=0.00),
     *                 @OA\Property(
     *                     property="items",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="sale_item_id", type="integer", example=5),
     *                         @OA\Property(property="product_id", type="integer", example=12),
     *                         @OA\Property(property="product_name", type="string", example="Panadol Extra 12s"),
     *                         @OA\Property(property="unit_price", type="number", format="float", example=85.00),
     *                         @OA\Property(property="original_quantity", type="number", format="float", example=3.0),
     *                         @OA\Property(property="already_refunded", type="number", format="float", example=1.0),
     *                         @OA\Property(property="remaining_refundable", type="number", format="float", example=2.0),
     *                         @OA\Property(property="is_fully_refunded", type="boolean", example=false),
     *                         @OA\Property(property="max_refund_amount", type="number", format="float", example=170.00)
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Sale not found")
     * )
     */
    public function getRefundableItems(Sale $sale): JsonResponse
    {
        $sale->loadMissing('items.product', 'items.productVariant');

        $items = $sale->items->map(function ($saleItem) {
            $alreadyRefunded = SaleRefundItem::getTotalRefundedForSaleItem($saleItem->id);
            $remaining = max(0, (float) $saleItem->quantity - $alreadyRefunded);

            // Calculate max refund amount proportional to remaining quantity
            $unitNetPrice = $saleItem->quantity > 0
                ? (float) $saleItem->subtotal / (float) $saleItem->quantity
                : (float) $saleItem->unit_price;

            return [
                'sale_item_id' => $saleItem->id,
                'product_id' => $saleItem->product_id,
                'product_variant_id' => $saleItem->product_variant_id,
                'product_name' => $saleItem->productVariant?->name ?? $saleItem->product?->name,
                'uom_id' => $saleItem->uom_id,
                'unit_price' => (float) $saleItem->unit_price,
                'tax_amount' => (float) $saleItem->tax_amount,
                'original_quantity' => (float) $saleItem->quantity,
                'already_refunded' => $alreadyRefunded,
                'remaining_refundable' => $remaining,
                'is_fully_refunded' => $remaining <= 0,
                'max_refund_amount' => round($unitNetPrice * $remaining, 2),
            ];
        });

        return ApiResponse::success('Refundable items retrieved successfully', [
            'sale_id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'can_refund' => $sale->canBeRefunded(),
            'total_refunded' => (float) $sale->total_refunded,
            'total_amount' => (float) $sale->total_amount,
            'items' => $items,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sales/{sale}/refunds",
     *     summary="Initiate and complete a refund",
     *     description="Processes a full or partial refund for a sale. The authenticated user must have the manager or owner role. Inventory is restored, loyalty points reversed, and ledger entries created atomically. Returns the completed refund record.",
     *     operationId="initiateRefund",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sale", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"store_id","reason","refund_method","items"},
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="reason", type="string", enum={"defective","wrong_item","not_as_described","expired","customer_changed_mind","duplicate_purchase","price_adjustment","other"}, example="defective"),
     *             @OA\Property(property="refund_method", type="string", enum={"cash","mpesa","card_reversal","bank_transfer","store_credit","credit_reduction","original_method"}, example="cash"),
     *             @OA\Property(property="notes", type="string", nullable=true, example="Customer returned damaged goods."),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     required={"sale_item_id","quantity_refunded","refund_amount"},
     *                     @OA\Property(property="sale_item_id", type="integer", example=5),
     *                     @OA\Property(property="quantity_refunded", type="number", format="float", example=2.0),
     *                     @OA\Property(property="refund_amount", type="number", format="float", example=170.00)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Refund processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Refund processed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="refund", ref="#/components/schemas/SaleRefundResource")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Insufficient permissions — manager or owner role required"),
     *     @OA\Response(response=422, description="Validation error or business rule violation")
     * )
     */
    public function initiateRefund(InitiateRefundRequest $request, Sale $sale): JsonResponse
    {
        abort_if(
            !auth()->user()->hasRole(['manager', 'owner']),
            403,
            'Insufficient permissions. Only managers and owners can process refunds.'
        );

        try {
            $refund = $this->refundService->processRefund($sale, $request->validated());

            return ApiResponse::created('Refund processed successfully', [
                'refund' => new SaleRefundResource($refund),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Refund processing failed. Please try again.');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/sales/{sale}/refunds",
     *     summary="List refunds for a sale",
     *     description="Returns all refunds associated with a specific sale.",
     *     operationId="listSaleRefunds",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sale", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Refunds retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="refunds", type="array",
     *                     @OA\Items(ref="#/components/schemas/SaleRefundResource")
     *                 ),
     *                 @OA\Property(property="total_refunded", type="number", format="float", example=170.00),
     *                 @OA\Property(property="can_refund", type="boolean", example=true)
     *             )
     *         )
     *     )
     * )
     */
    public function listSaleRefunds(Sale $sale): JsonResponse
    {
        $refunds = $sale->refunds()
            ->with(['items.saleItem', 'processedBy', 'approvedBy', 'exchangeSale', 'store', 'customer'])
            ->latest()
            ->get();

        return ApiResponse::success('Refunds retrieved successfully', [
            'refunds' => SaleRefundResource::collection($refunds),
            'total_refunded' => (float) $sale->total_refunded,
            'can_refund' => $sale->canBeRefunded(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/sales/{sale}/exchange",
     *     summary="Process an exchange transaction",
     *     description="Atomically processes a return (refunded as store credit) and creates a new sale for the exchange items. Both transactions are linked via exchange_sale_id. The authenticated user must have the manager or owner role.",
     *     operationId="initiateExchange",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sale", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"store_id","reason","items","exchange_items","exchange_payments"},
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="reason", type="string", example="wrong_item"),
     *             @OA\Property(property="notes", type="string", nullable=true),
     *             @OA\Property(property="items", type="array", description="Items to return from original sale",
     *                 @OA\Items(
     *                     @OA\Property(property="sale_item_id", type="integer"),
     *                     @OA\Property(property="quantity_refunded", type="number"),
     *                     @OA\Property(property="refund_amount", type="number")
     *                 )
     *             ),
     *             @OA\Property(property="exchange_items", type="array", description="New items for the exchange sale",
     *                 @OA\Items(
     *                     @OA\Property(property="product_id", type="integer", nullable=true),
     *                     @OA\Property(property="variant_id", type="integer", nullable=true),
     *                     @OA\Property(property="bundle_id", type="integer", nullable=true),
     *                     @OA\Property(property="quantity", type="number"),
     *                     @OA\Property(property="uom_id", type="integer")
     *                 )
     *             ),
     *             @OA\Property(property="exchange_payments", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="amount", type="number"),
     *                     @OA\Property(property="payment_method", type="string"),
     *                     @OA\Property(property="reference_number", type="string", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="coupon_code", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Exchange processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="refund", ref="#/components/schemas/SaleRefundResource"),
     *                 @OA\Property(property="sale", type="object", description="Newly created exchange sale",
     *                     @OA\Property(property="id", type="integer", example=11),
     *                     @OA\Property(property="sale_number", type="string", example="SALE-01-202503-000011"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=300.00),
     *                     @OA\Property(property="payment_status", type="string", example="paid")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Insufficient permissions"),
     *     @OA\Response(response=422, description="Validation error or business rule violation")
     * )
     */
    public function initiateExchange(InitiateExchangeRequest $request, Sale $sale): JsonResponse
    {
        abort_if(
            !Auth::user()->hasRole(['manager', 'owner']),
            403,
            'Insufficient permissions. Only managers and owners can process exchanges.'
        );

        try {
            $result = $this->refundService->processExchange($sale, $request->validated());

            return ApiResponse::created('Exchange processed successfully', [
                'refund' => new SaleRefundResource($result['refund']),
                'sale' => new SaleResource($result['sale']->load(['items', 'payments', 'store', 'customer', 'servedBy'])),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Exchange processing failed', [
                'sale_id' => $sale->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::serverError('Exchange processing failed. Please try again.');
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/refunds",
     *     summary="List all refunds",
     *     description="Returns a paginated list of all refunds across the tenant with optional filters.",
     *     operationId="listRefunds",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="store_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="customer_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="reason", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="refund_method", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="from_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="to_date", in="query", required=false, @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Response(response=200, description="Refunds retrieved successfully")
     * )
     */
    public function index(ListRefundsRequest $request): JsonResponse
    {
        $query = SaleRefund::query()
            ->with(['originalSale', 'store', 'customer', 'processedBy', 'items'])
            ->latest();

        if ($request->filled('store_id')) {
            $query->byStore($request->integer('store_id'));
        }

        if ($request->filled('customer_id')) {
            $query->byCustomer($request->integer('customer_id'));
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('refund_method')) {
            $query->where('refund_method', $request->refund_method);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->byDateRange($request->from_date, $request->to_date);
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        $refunds = $query->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated(SaleRefundResource::collection($refunds), 'Refunds retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/refunds/{refund}",
     *     summary="Get a single refund",
     *     description="Returns full details of a specific refund including all line items.",
     *     operationId="showRefund",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="refund", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Refund retrieved successfully"),
     *     @OA\Response(response=404, description="Refund not found")
     * )
     */
    public function show(SaleRefund $refund): JsonResponse
    {
        $refund->loadMissing([
            'items.saleItem.product',
            'items.saleItem.productVariant',
            'originalSale',
            'store',
            'customer',
            'processedBy',
            'approvedBy',
            'exchangeSale',
        ]);

        return ApiResponse::success('Refund retrieved successfully', [
            'refund' => new SaleRefundResource($refund),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/refunds/{refund}/receipt",
     *     summary="Generate refund receipt data",
     *     description="Returns structured receipt data for a completed refund. The client is responsible for rendering or printing the receipt.",
     *     operationId="generateRefundReceipt",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="refund", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Receipt data generated successfully")
     * )
     */
    public function generateReceipt(SaleRefund $refund): JsonResponse
    {
        $refund->loadMissing([
            'items.saleItem.product',
            'items.saleItem.productVariant',
            'originalSale',
            'store',
            'customer',
            'processedBy',
        ]);

        $receiptData = [
            'receipt_type' => 'REFUND',
            'refund_number' => $refund->refund_number,
            'original_sale_number' => $refund->originalSale?->sale_number,
            'refund_date' => $refund->refund_date?->toDateString(),
            'processed_at' => $refund->processed_at?->toIso8601String(),

            'store' => [
                'name' => $refund->store->name,
                'code' => $refund->store->code,
            ],

            'customer' => $refund->customer ? [
                'name' => $refund->customer->name,
                'phone' => $refund->customer->phone,
            ] : null,

            'items' => $refund->items->map(fn ($item) => [
                'product_name' => $item->product_name,
                'quantity_refunded' => (float) $item->quantity_refunded,
                'unit_refund_price' => (float) $item->unit_refund_price,
                'refund_amount' => (float) $item->refund_amount,
            ]),

            'summary' => [
                'total_refund_amount' => (float) $refund->refund_amount,
                'refund_method' => $refund->refund_method_label,
                'reason' => $refund->reason_label,
            ],

            'processed_by' => $refund->processedBy?->name,
        ];

        return ApiResponse::success('Receipt data generated successfully', [
            'receipt' => $receiptData,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/refunds/{refund}/cancel",
     *     summary="Cancel a refund",
     *     description="Cancels a refund that has not yet completed (status = processing). Completed refunds cannot be cancelled. Requires manager or owner role.",
     *     operationId="cancelRefund",
     *     tags={"Sales - Refunds"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="refund", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Refund cancelled successfully"),
     *     @OA\Response(response=403, description="Insufficient permissions"),
     *     @OA\Response(response=422, description="Cannot cancel a completed refund")
     * )
     */
    public function cancel(SaleRefund $refund): JsonResponse
    {
        abort_if(
            !auth()->user()->hasRole(['manager', 'owner']),
            403,
            'Insufficient permissions. Only managers and owners can cancel refunds.'
        );

        try {
            $refund = $this->refundService->cancelRefund($refund);

            return ApiResponse::success('Refund cancelled successfully', [
                'refund' => new SaleRefundResource($refund),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        }
    }
}
