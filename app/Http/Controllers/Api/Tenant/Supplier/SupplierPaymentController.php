<?php

namespace App\Http\Controllers\Api\Tenant\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Supplier\StoreSupplierPaymentRequest;
use App\Http\Resources\Tenant\Supplier\SupplierPaymentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\SupplierPayment;
use App\Services\Tenant\Supplier\SupplierPaymentService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierPaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private SupplierPaymentService $supplierPaymentService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/supplier-payments",
     *     summary="Record supplier payment",
     *     description="Record a new payment to a supplier with optional link to purchase order. Supports file upload for payment receipt (PDF, JPG, PNG, max 5MB). Use multipart/form-data when uploading a receipt, otherwise use application/json.",
     *     operationId="storeSupplierPayment",
     *     tags={"Supplier Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 required={"supplier_id", "payment_date", "amount", "payment_method"},
     *                 @OA\Property(property="supplier_id", type="integer", example=1, description="ID of the supplier receiving payment"),
     *                 @OA\Property(property="purchase_order_id", type="integer", example=5, nullable=true, description="Optional purchase order ID to link payment to"),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11", description="Date of payment"),
     *                 @OA\Property(property="amount", type="number", format="float", example=50000.00, description="Payment amount"),
     *                 @OA\Property(property="payment_method", type="string", enum={"cash","bank_transfer","mpesa","cheque","card","other"}, example="mpesa", description="Method of payment"),
     *                 @OA\Property(property="reference_number", type="string", example="RGH12345XYZ", nullable=true, description="Payment reference/transaction number"),
     *                 @OA\Property(property="notes", type="string", example="Payment for January delivery", nullable=true, description="Additional notes about the payment")
     *             )
     *         ),
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"supplier_id", "payment_date", "amount", "payment_method"},
     *                 @OA\Property(property="supplier_id", type="integer", example=1),
     *                 @OA\Property(property="purchase_order_id", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                 @OA\Property(property="amount", type="number", format="float", example=90000.00),
     *                 @OA\Property(property="payment_method", type="string", enum={"cash","bank_transfer","mpesa","cheque","card","other"}, example="mpesa"),
     *                 @OA\Property(property="reference_number", type="string", example="RGH12345XYZ", nullable=true),
     *                 @OA\Property(property="notes", type="string", example="January", nullable=true),
     *                 @OA\Property(property="receipt", type="string", format="binary", description="Receipt file (PDF, JPG, PNG, max 5MB)", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="payment_number", type="string", example="PAY-SUP-2026-0002", description="Auto-generated payment number"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="current_outstanding", type="number", format="float", example=135000, description="Current outstanding balance after this payment"),
     *                     @OA\Property(property="credit_limit", type="number", format="float", example=1000000)
     *                 ),
     *                 @OA\Property(
     *                     property="purchase_order",
     *                     type="object",
     *                     nullable=true,
     *                     description="Present only if payment is linked to a purchase order",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=96780),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=90000, description="Total amount paid towards this PO"),
     *                     @OA\Property(property="outstanding", type="number", format="float", example=6780, description="Remaining outstanding amount"),
     *                     @OA\Property(property="payment_status", type="string", example="partially_paid", enum={"pending", "partially_paid", "paid"}),
     *                     @OA\Property(property="payment_status_label", type="string", example="Partially Paid")
     *                 ),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                 @OA\Property(property="amount", type="number", format="float", example=90000),
     *                 @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                 @OA\Property(property="payment_method_label", type="string", example="M-Pesa"),
     *                 @OA\Property(property="reference_number", type="string", nullable=true, example="RGH12345XYZ"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="January"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=true, description="Whether a receipt file was uploaded"),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/supplier-payments/receipts/receipt_1768142362.jpg", description="URL to the uploaded receipt (present only if has_receipt is true)"),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T14:39:22.238752Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d196dc7c-42d5-4904-9f9a-fb5fba745ed0"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier or Purchase Order not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid input data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="supplier_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The supplier_id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount field must be a number.")
     *                 ),
     *                 @OA\Property(
     *                     property="receipt",
     *                     type="array",
     *                     @OA\Items(type="string", example="The receipt must be a file of type: pdf, jpg, png.")
     *                 )
     *             )
     *         )
     *     )
     * )*/
    public function store(StoreSupplierPaymentRequest $request): JsonResponse
    {
        $this->authorize('create', SupplierPayment::class);

        try {
            $payment = $this->supplierPaymentService->recordPayment(
                $request->validated()
            );

            return ApiResponse::created(
                'Payment recorded successfully',
                new SupplierPaymentResource($payment)
            );
        } catch (\RuntimeException $e) {
            Log::error('Failed to record supplier payment - business rule violation', [
                'error' => $e->getMessage(),
                'data' => $request->safe()->except(['receipt']),
                'user_id' => $request->user()->id,
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::error(
                'Failed to record payment: ' . $e->getMessage(),
                null,
                400
            );
        } catch (\Exception $e) {
            Log::error('Failed to record supplier payment - unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->safe()->except(['receipt']),
                'user_id' => $request->user()->id,
                'tenant_id' => tenant()->id,
            ]);

            return ApiResponse::serverError(
                'An unexpected error occurred while recording the payment. Please try again.'
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/supplier-payments",
     *     summary="List all supplier payments",
     *     description="Get paginated list of supplier payments with optional filters. Payments can be filtered by supplier, purchase order, payment method, and date range.",
     *     operationId="indexSupplierPayments",
     *     tags={"Supplier Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="supplier_id",
     *         in="query",
     *         description="Filter by supplier ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="purchase_order_id",
     *         in="query",
     *         description="Filter by purchase order ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method",
     *         required=false,
     *         @OA\Schema(type="string", enum={"cash","bank_transfer","mpesa","cheque","card","other"})
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Filter payments from this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="to_date",
     *         in="query",
     *         description="Filter payments to this date",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payments retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payments retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="payment_number", type="string", example="PAY-SUP-2026-0002"),
     *                         @OA\Property(
     *                             property="supplier",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                             @OA\Property(property="current_outstanding", type="number", format="float", example=135000),
     *                             @OA\Property(property="credit_limit", type="number", format="float", example=1000000)
     *                         ),
     *                         @OA\Property(
     *                             property="purchase_order",
     *                             type="object",
     *                             nullable=true,
     *                             description="Present only if payment is linked to a purchase order",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                             @OA\Property(property="total_amount", type="number", format="float", example=96780),
     *                             @OA\Property(property="amount_paid", type="number", format="float", example=90000),
     *                             @OA\Property(property="outstanding", type="number", format="float", example=6780),
     *                             @OA\Property(property="payment_status", type="string", example="partially_paid"),
     *                             @OA\Property(property="payment_status_label", type="string", example="Partially Paid")
     *                         ),
     *                         @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                         @OA\Property(property="amount", type="number", format="float", example=90000),
     *                         @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                         @OA\Property(property="payment_method_label", type="string", example="M-Pesa"),
     *                         @OA\Property(property="reference_number", type="string", nullable=true, example="RGH12345XYZ"),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="January"),
     *                         @OA\Property(property="has_receipt", type="boolean", example=true),
     *                         @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/supplier-payments/receipts/receipt_1768142362.jpg"),
     *                         @OA\Property(
     *                             property="created_by",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T14:46:55.430640Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="79eeaa0e-296f-46d1-8d9d-89a8fbf34265"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     )
     * )*/
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $filters = $request->only([
            'supplier_id',
            'purchase_order_id',
            'payment_method',
            'from_date',
            'to_date',
            'per_page',
        ]);

        // If supplier_id provided, get supplier payments
        if (!empty($filters['supplier_id'])) {
            $payments = $this->supplierPaymentService->getSupplierPayments(
                $filters['supplier_id'],
                $filters
            );
        } else {
            // Get all payments with filters
            $query = SupplierPayment::withDetails();

            if (!empty($filters['purchase_order_id'])) {
                $query->byPurchaseOrder($filters['purchase_order_id']);
            }

            if (!empty($filters['payment_method'])) {
                $query->byPaymentMethod($filters['payment_method']);
            }

            if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
                $query->byDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);
            }

            $query->recent();

            $payments = $query->paginate($filters['per_page'] ?? 20);
        }

        return ApiResponse::paginated(
            SupplierPaymentResource::collection($payments),
            'Payments retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/supplier-payments/{id}",
     *     summary="Get single payment details",
     *     description="Retrieve detailed information about a specific supplier payment by its ID. Includes supplier details, optional purchase order information, payment details, and receipt URL if available.",
     *     operationId="showSupplierPayment",
     *     tags={"Supplier Payments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="payment_number", type="string", example="PAY-SUP-2026-0002"),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="current_outstanding", type="number", format="float", example=135000),
     *                     @OA\Property(property="credit_limit", type="number", format="float", example=1000000)
     *                 ),
     *                 @OA\Property(
     *                     property="purchase_order",
     *                     type="object",
     *                     nullable=true,
     *                     description="Present only if payment is linked to a purchase order",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=96780),
     *                     @OA\Property(property="amount_paid", type="number", format="float", example=90000),
     *                     @OA\Property(property="outstanding", type="number", format="float", example=6780),
     *                     @OA\Property(property="payment_status", type="string", example="partially_paid"),
     *                     @OA\Property(property="payment_status_label", type="string", example="Partially Paid")
     *                 ),
     *                 @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                 @OA\Property(property="amount", type="number", format="float", example=90000),
     *                 @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                 @OA\Property(property="payment_method_label", type="string", example="M-Pesa"),
     *                 @OA\Property(property="reference_number", type="string", nullable=true, example="RGH12345XYZ"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="January"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=true),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/supplier-payments/receipts/receipt_1768142362.jpg"),
     *                 @OA\Property(
     *                     property="created_by",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe")
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T14:48:10.821906Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cfb593b5-5919-40c7-87be-d53502ed0f3b"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment not found"
     *     )
     * )*/
    public function show(int $id): JsonResponse
    {
        $payment = SupplierPayment::withDetails()->findOrFail($id);

        $this->authorize('view', $payment);

        return ApiResponse::success(
            'Payment retrieved successfully',
            new SupplierPaymentResource($payment)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/suppliers/{supplierId}/payments",
     *     summary="Get supplier payment history",
     *     description="Retrieve all payments made to a specific supplier with pagination. Returns complete payment details including linked purchase orders, receipts, and payment information.",
     *     operationId="supplierPayments",
     *     tags={"Supplier Payments", "Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="supplierId",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=20, example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Supplier payment history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Supplier payment history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="payment_number", type="string", example="PAY-SUP-2026-0002"),
     *                         @OA\Property(
     *                             property="supplier",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                             @OA\Property(property="current_outstanding", type="number", format="float", example=135000),
     *                             @OA\Property(property="credit_limit", type="number", format="float", example=1000000)
     *                         ),
     *                         @OA\Property(
     *                             property="purchase_order",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                             @OA\Property(property="total_amount", type="number", format="float", example=96780),
     *                             @OA\Property(property="amount_paid", type="number", format="float", example=90000),
     *                             @OA\Property(property="outstanding", type="number", format="float", example=6780),
     *                             @OA\Property(property="payment_status", type="string", example="partially_paid"),
     *                             @OA\Property(property="payment_status_label", type="string", example="Partially Paid")
     *                         ),
     *                         @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                         @OA\Property(property="amount", type="number", format="float", example=90000),
     *                         @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                         @OA\Property(property="payment_method_label", type="string", example="M-Pesa"),
     *                         @OA\Property(property="reference_number", type="string", nullable=true, example="RGH12345XYZ"),
     *                         @OA\Property(property="notes", type="string", nullable=true, example="January"),
     *                         @OA\Property(property="has_receipt", type="boolean", example=true),
     *                         @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/supplier-payments/receipts/receipt_1768142362.jpg"),
     *                         @OA\Property(
     *                             property="created_by",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe")
     *                         ),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-11T14:39:22.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=20),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T15:04:40.044760Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ea554bbe-a0c9-49b0-9214-3ccf946fc3f0"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found"
     *     )
     * )*/
    public function supplierPayments(int $supplierId, Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $filters = $request->only([
            'purchase_order_id',
            'payment_method',
            'from_date',
            'to_date',
            'per_page',
        ]);

        $payments = $this->supplierPaymentService->getSupplierPayments(
            $supplierId,
            $filters
        );

        return ApiResponse::paginated(
            SupplierPaymentResource::collection($payments),
            'Supplier payment history retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/suppliers/{supplierId}/payment-summary",
     *     summary="Get supplier payment summary",
     *     description="Get aggregated payment statistics for a supplier including total amount paid, payment count, and breakdown by payment method. Optionally filter by specific purchase order.",
     *     operationId="supplierPaymentSummary",
     *     tags={"Supplier Payments", "Suppliers"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="supplierId",
     *         in="path",
     *         description="Supplier ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="purchase_order_id",
     *         in="query",
     *         description="Filter summary by specific purchase order",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment summary retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="supplier_id", type="integer", example=1, description="ID of the supplier"),
     *                 @OA\Property(property="purchase_order_id", type="integer", nullable=true, example=null, description="Purchase order ID if filtered, null otherwise"),
     *                 @OA\Property(property="total_paid", type="number", format="float", example=115000, description="Total amount paid to supplier"),
     *                 @OA\Property(property="payment_count", type="integer", example=2, description="Total number of payments made"),
     *                 @OA\Property(
     *                     property="by_method",
     *                     type="array",
     *                     description="Breakdown of payments by payment method",
     *                     @OA\Items(
     *                         @OA\Property(property="method", type="string", example="mpesa", description="Payment method code"),
     *                         @OA\Property(property="method_label", type="string", example="M-Pesa", description="Human-readable payment method label"),
     *                         @OA\Property(property="count", type="integer", example=2, description="Number of payments using this method"),
     *                         @OA\Property(property="total", type="number", format="float", example=115000, description="Total amount paid using this method")
     *                     )
     *                 ),
     *                 @OA\Property(property="currency", type="string", example="KES", description="Currency code")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T15:15:02.717815Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3d362a6f-690e-4e83-92e5-ac23e4882498"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Supplier not found"
     *     )
     * )*/
    public function supplierPaymentSummary(int $supplierId, Request $request): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $poId = $request->query('purchase_order_id');

        $summary = $this->supplierPaymentService->getSupplierPaymentSummary(
            $supplierId,
            $poId
        );

        return ApiResponse::success(
            'Payment summary retrieved successfully',
            $summary
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/purchase-orders/{poId}/payments",
     *     summary="Get purchase order payment history",
     *     description="Retrieve all payments made for a specific purchase order. Returns complete payment details including supplier information, payment methods, receipts, and current PO payment status.",
     *     operationId="purchaseOrderPayments",
     *     tags={"Supplier Payments", "Purchase Orders"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="poId",
     *         in="path",
     *         description="Purchase Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Purchase order payment history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Purchase order payment history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="payment_number", type="string", example="PAY-SUP-2026-0003"),
     *                     @OA\Property(
     *                         property="supplier",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="current_outstanding", type="number", format="float", example=128220),
     *                         @OA\Property(property="credit_limit", type="number", format="float", example=1000000)
     *                     ),
     *                     @OA\Property(
     *                         property="purchase_order",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="po_number", type="string", example="PO-2025-0001"),
     *                         @OA\Property(property="total_amount", type="number", format="float", example=96780),
     *                         @OA\Property(property="amount_paid", type="number", format="float", example=96780, description="Total amount paid (updated with current payment)"),
     *                         @OA\Property(property="outstanding", type="number", format="float", example=0, description="Remaining outstanding amount"),
     *                         @OA\Property(property="payment_status", type="string", example="paid", enum={"pending", "partially_paid", "paid"}),
     *                         @OA\Property(property="payment_status_label", type="string", example="Fully Paid")
     *                     ),
     *                     @OA\Property(property="payment_date", type="string", format="date", example="2025-01-11"),
     *                     @OA\Property(property="amount", type="number", format="float", example=6780),
     *                     @OA\Property(property="payment_method", type="string", example="mpesa"),
     *                     @OA\Property(property="payment_method_label", type="string", example="M-Pesa"),
     *                     @OA\Property(property="reference_number", type="string", nullable=true, example="RGH12345XYZ"),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="January"),
     *                     @OA\Property(property="has_receipt", type="boolean", example=true),
     *                     @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/supplier-payments/receipts/receipt_1768144774.jpg"),
     *                     @OA\Property(
     *                         property="created_by",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-11T15:19:34.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-11T15:19:34.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-11T15:19:43.815259Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a6de1158-5478-4c99-9524-82cca8ae0afc"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Purchase order not found"
     *     )
     * )*/
    public function purchaseOrderPayments(int $poId): JsonResponse
    {
        $this->authorize('viewAny', SupplierPayment::class);

        $payments = $this->supplierPaymentService->getPurchaseOrderPayments($poId);

        return ApiResponse::success(
            'Purchase order payment history retrieved successfully',
            SupplierPaymentResource::collection($payments)
        );
    }
}
