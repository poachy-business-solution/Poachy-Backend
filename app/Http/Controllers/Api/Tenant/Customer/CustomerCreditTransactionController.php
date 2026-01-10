<?php

namespace App\Http\Controllers\Api\Tenant\Customer;

use App\Enums\Tenant\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\ListCreditTransactionsRequest;
use App\Http\Requests\Tenant\Customer\RecordAdjustmentRequest;
use App\Http\Requests\Tenant\Customer\RecordPaymentRequest;
use App\Http\Resources\Tenant\Customer\CustomerCreditTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerCreditTransaction;
use App\Services\Tenant\Sales\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CustomerCreditTransactionController extends Controller
{
    public function __construct(
        protected CreditService $creditService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/credit-transactions",
     *     summary="Get list of credit transactions",
     *     description="Retrieve a paginated list of credit transactions with optional filters and sorting. Returns transaction details along with summary statistics including outstanding debt and collection rates.",
     *     operationId="getCreditTransactions",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="transaction_type",
     *         in="query",
     *         description="Filter by transaction type (sale_on_credit, payment, adjustment, write_off)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"sale_on_credit", "payment", "adjustment", "write_off"})
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method (cash, mpesa, card, etc.)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"cash", "mpesa", "card", "bank_transfer"})
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter transactions from this date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter transactions until this date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="reference_type",
     *         in="query",
     *         description="Filter by reference type (e.g., App\Models\Tenant\Sale)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of records per page (default: 15, max: 100)",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field (created_at, amount, balance_after)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "amount", "balance_after"}, example="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction (asc, desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit transactions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit transactions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=7),
     *                             @OA\Property(property="customer_id", type="integer", example=4),
     *                             @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                             @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                             @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                             @OA\Property(property="transaction_type", type="string", example="sale_on_credit"),
     *                             @OA\Property(property="amount", type="number", format="float", example=16650, description="Transaction amount (negative for credits/payments)"),
     *                             @OA\Property(property="absolute_amount", type="number", format="float", example=16650, description="Absolute value of amount"),
     *                             @OA\Property(property="balance_after", type="number", format="float", example=246600),
     *                             @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                             @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                             @OA\Property(
     *                                 property="reference",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="type", type="string", example="sale"),
     *                                 @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                                 @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                                 @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09"),
     *                                 @OA\Property(property="payment_status", type="string", example="partially_paid")
     *                             ),
     *                             @OA\Property(property="payment_method", type="string", nullable=true, example=null),
     *                             @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Credit sale - INV-STR-2025-74622-2026-01-000009"),
     *                             @OA\Property(property="created_by", type="integer", example=3),
     *                             @OA\Property(property="created_by_name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="is_debit", type="boolean", example=true),
     *                             @OA\Property(property="is_credit", type="boolean", example=false),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="links",
     *                         type="object",
     *                         @OA\Property(property="first", type="string", example="http://techhaven.localhost/api/v1/tenant/credit-transactions?page=1"),
     *                         @OA\Property(property="last", type="string", example="http://techhaven.localhost/api/v1/tenant/credit-transactions?page=1"),
     *                         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                         @OA\Property(property="next", type="string", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="from", type="integer", example=1),
     *                         @OA\Property(property="last_page", type="integer", example=1),
     *                         @OA\Property(
     *                             property="links",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="url", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/credit-transactions?page=1"),
     *                                 @OA\Property(property="label", type="string", example="1"),
     *                                 @OA\Property(property="page", type="integer", nullable=true, example=1),
     *                                 @OA\Property(property="active", type="boolean", example=true)
     *                             )
     *                         ),
     *                         @OA\Property(property="path", type="string", example="http://techhaven.localhost/api/v1/tenant/credit-transactions"),
     *                         @OA\Property(property="per_page", type="integer", example=15),
     *                         @OA\Property(property="to", type="integer", example=7),
     *                         @OA\Property(property="total", type="integer", example=7)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_credit_sales", type="number", format="float", example=529988.52),
     *                     @OA\Property(property="total_payments", type="number", format="float", example=0),
     *                     @OA\Property(property="total_outstanding", type="number", format="float", example=296600),
     *                     @OA\Property(property="total_write_offs", type="number", format="float", example=0),
     *                     @OA\Property(property="unique_credit_customers", type="integer", example=1),
     *                     @OA\Property(property="avg_debt_per_customer", type="number", format="float", example=296600),
     *                     @OA\Property(property="collection_rate", type="number", format="float", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T09:58:23.688548Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="82488ced-1dd7-4d53-a280-089c4e65f842"),
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
    public function index(ListCreditTransactionsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Build query with filters
            $query = CustomerCreditTransaction::query()
                ->with(['customer:id,name,phone,email,current_debt,credit_limit', 'reference', 'createdBy:id,name'])
                ->select('customer_credit_transactions.*');

            // Apply filters
            if (!empty($validated['customer_id'])) {
                $query->where('customer_id', $validated['customer_id']);
            }

            if (!empty($validated['transaction_type'])) {
                $query->where('transaction_type', $validated['transaction_type']);
            }

            if (!empty($validated['payment_method'])) {
                $query->where('payment_method', $validated['payment_method']);
            }

            if (!empty($validated['reference_type'])) {
                $query->where('reference_type', $validated['reference_type']);
            }

            if (!empty($validated['date_from'])) {
                $query->whereDate('created_at', '>=', $validated['date_from']);
            }

            if (!empty($validated['date_to'])) {
                $query->whereDate('created_at', '<=', $validated['date_to']);
            }

            // Sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($validated['per_page'] ?? 15, 100);
            $transactions = $query->paginate($perPage);

            // Calculate analytics summary
            $summary = $this->creditService->calculateSummary($validated);

            return ApiResponse::success(
                'Credit transactions retrieved successfully',
                [
                    'transactions' => CustomerCreditTransactionResource::collection($transactions)->response()->getData(),
                    'summary' => $summary,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve credit transactions', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve credit transactions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/credit-transactions/{id}",
     *     summary="Get a specific credit transaction",
     *     description="Retrieve detailed information about a single credit transaction by its ID. The response structure varies based on transaction type - sale_on_credit transactions have reference objects, while payment transactions have payment methods.",
     *     operationId="getCreditTransactionById",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Credit transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=7)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit transaction retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit transaction retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=7),
     *                 @OA\Property(property="customer_id", type="integer", example=4),
     *                 @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                 @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                 @OA\Property(property="transaction_type", type="string", example="sale_on_credit", description="Transaction type: sale_on_credit, payment, adjustment, or write_off"),
     *                 @OA\Property(property="amount", type="number", format="float", example=16650, description="Transaction amount (negative for credits/payments)"),
     *                 @OA\Property(property="absolute_amount", type="number", format="float", example=16650, description="Absolute value of amount"),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=246600, description="Customer's debt balance after this transaction"),
     *                 @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                 @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                 @OA\Property(
     *                     property="reference",
     *                     type="object",
     *                     nullable=true,
     *                     description="Reference object for sale_on_credit transactions, null for payment/adjustment transactions",
     *                     @OA\Property(property="type", type="string", example="sale"),
     *                     @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                     @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                     @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09"),
     *                     @OA\Property(property="payment_status", type="string", example="partially_paid")
     *                 ),
     *                 @OA\Property(property="payment_method", type="string", nullable=true, example=null, description="Payment method for payment transactions (cash, mpesa, card, bank_transfer)"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null, description="Payment reference/transaction ID for payment transactions"),
     *                 @OA\Property(property="notes", type="string", nullable=true, example="Credit sale - INV-STR-2025-74622-2026-01-000009"),
     *                 @OA\Property(property="created_by", type="integer", example=3),
     *                 @OA\Property(property="created_by_name", type="string", example="Jane Cashier"),
     *                 @OA\Property(property="is_debit", type="boolean", example=true, description="True if transaction increases debt"),
     *                 @OA\Property(property="is_credit", type="boolean", example=false, description="True if transaction decreases debt"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T10:16:38.761415Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3bc420d7-0753-4f44-aa7d-8d9c1316f875"),
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
     *         description="Credit transaction not found"
     *     )
     * )*/
    public function show(int $id): JsonResponse
    {
        try {
            $transaction = CustomerCreditTransaction::with(['customer', 'reference', 'createdBy'])
                ->findOrFail($id);

            return ApiResponse::success(
                'Credit transaction retrieved successfully',
                new CustomerCreditTransactionResource($transaction)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Credit transaction not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve credit transaction', [
                'tenant_id' => tenant()->id,
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve credit transaction',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers/{customer_id}/credit-transactions",
     *     summary="Get credit transactions for a specific customer",
     *     description="Retrieve paginated credit transaction history for a specific customer along with their current debt, credit limit, available credit, and comprehensive debt summary statistics.",
     *     operationId="getCustomerCreditTransactions",
     *     tags={"Credit Transactions", "Customers"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="path",
     *         description="Customer ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Records per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=20)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Customer credit history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer credit history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="phone", type="string", example="+254712345602"),
     *                     @OA\Property(property="current_debt", type="string", example="246600.00"),
     *                     @OA\Property(property="credit_limit", type="string", example="500000.00"),
     *                     @OA\Property(property="available_credit", type="number", format="float", example=253400)
     *                 ),
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=7),
     *                             @OA\Property(property="customer_id", type="integer", example=4),
     *                             @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                             @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                             @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                             @OA\Property(property="transaction_type", type="string", example="sale_on_credit"),
     *                             @OA\Property(property="amount", type="number", format="float", example=16650),
     *                             @OA\Property(property="absolute_amount", type="number", format="float", example=16650),
     *                             @OA\Property(property="balance_after", type="number", format="float", example=246600),
     *                             @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                             @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                             @OA\Property(
     *                                 property="reference",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="type", type="string", example="sale"),
     *                                 @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                                 @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                                 @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09"),
     *                                 @OA\Property(property="payment_status", type="string", example="partially_paid")
     *                             ),
     *                             @OA\Property(property="payment_method", type="string", nullable=true, example=null),
     *                             @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                             @OA\Property(property="notes", type="string", nullable=true, example="Credit sale - INV-STR-2025-74622-2026-01-000009"),
     *                             @OA\Property(property="created_by", type="integer", example=3),
     *                             @OA\Property(property="created_by_name", type="string", example="Jane Cashier"),
     *                             @OA\Property(property="is_debit", type="boolean", example=true),
     *                             @OA\Property(property="is_credit", type="boolean", example=false),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="links",
     *                         type="object",
     *                         @OA\Property(property="first", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/credit-transactions?page=1"),
     *                         @OA\Property(property="last", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/credit-transactions?page=1"),
     *                         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                         @OA\Property(property="next", type="string", nullable=true, example=null)
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="from", type="integer", example=1),
     *                         @OA\Property(property="last_page", type="integer", example=1),
     *                         @OA\Property(
     *                             property="links",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="url", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/customers/4/credit-transactions?page=1"),
     *                                 @OA\Property(property="label", type="string", example="1"),
     *                                 @OA\Property(property="page", type="integer", nullable=true, example=1),
     *                                 @OA\Property(property="active", type="boolean", example=true)
     *                             )
     *                         ),
     *                         @OA\Property(property="path", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/credit-transactions"),
     *                         @OA\Property(property="per_page", type="integer", example=20),
     *                         @OA\Property(property="to", type="integer", example=7),
     *                         @OA\Property(property="total", type="integer", example=7)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="debt_summary",
     *                     type="object",
     *                     @OA\Property(property="total_credit_sales", type="number", format="float", example=529988.52),
     *                     @OA\Property(property="total_payments", type="number", format="float", example=0),
     *                     @OA\Property(property="total_adjustments", type="number", format="float", example=0),
     *                     @OA\Property(property="current_debt", type="number", format="float", example=246600),
     *                     @OA\Property(property="credit_limit", type="number", format="float", example=500000),
     *                     @OA\Property(property="available_credit", type="number", format="float", example=253400),
     *                     @OA\Property(property="credit_utilization", type="number", format="float", example=49.32, description="Credit utilization percentage")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T10:23:18.729376Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c77dd407-dd0d-4a67-9b3f-636821e69a98"),
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
     *         description="Customer not found"
     *     )
     * )*/
    public function customerHistory(int $customerId): JsonResponse
    {
        try {
            $customer = Customer::findOrFail($customerId);

            $perPage = min(request('per_page', 20), 100);

            $transactions = CustomerCreditTransaction::byCustomer($customerId)
                ->with(['reference', 'createdBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Calculate debt summary
            $debtSummary = $this->creditService->calculateCustomerDebtSummary($customerId, $customer);

            return ApiResponse::success(
                'Customer credit history retrieved successfully',
                [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'current_debt' => $customer->current_debt,
                        'credit_limit' => $customer->credit_limit,
                        'available_credit' => max(0, $customer->credit_limit - $customer->current_debt),
                    ],
                    'transactions' => CustomerCreditTransactionResource::collection($transactions)->response()->getData(),
                    'debt_summary' => $debtSummary,
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve customer credit history', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve customer credit history',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/credit-transactions/record-payment",
     *     summary="Record a credit payment from a customer",
     *     description="Record a payment made by a customer towards their credit balance. This creates a credit transaction record and reduces the customer's outstanding debt.",
     *     operationId="recordCreditPayment",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "amount", "payment_method"},
     *             @OA\Property(property="customer_id", type="integer", example=5, description="ID of the customer making the payment"),
     *             @OA\Property(property="amount", type="number", format="float", example=5000.00, description="Payment amount"),
     *             @OA\Property(property="payment_method", type="string", enum={"cash", "mpesa", "card", "bank_transfer"}, example="mpesa", description="Method of payment"),
     *             @OA\Property(property="payment_reference", type="string", example="REF123456", description="Payment reference/transaction ID", nullable=true),
     *             @OA\Property(property="notes", type="string", example="Payment for credit sales", description="Additional notes about the payment", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit payment recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit payment recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transaction_id", type="integer", example=8, description="ID of the created payment transaction"),
     *                 @OA\Property(property="customer_id", type="integer", example=4),
     *                 @OA\Property(property="amount_paid", type="number", format="float", example=10000),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="new_debt_balance", type="string", example="236600.00", description="Customer's new outstanding debt balance"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null)
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T11:13:48.454886Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3b81c9af-422a-411b-9733-2c920f2abf7b"),
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
     *         description="Customer not found"
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
     *                     property="customer_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The customer_id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount field must be a number.")
     *                 ),
     *                 @OA\Property(
     *                     property="payment_method",
     *                     type="array",
     *                     @OA\Items(type="string", example="The payment_method field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )*/
    public function recordPayment(RecordPaymentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $customer = Customer::findOrFail($validated['customer_id']);

            // Validate payment amount doesn't exceed debt
            if ($validated['amount'] > $customer->current_debt) {
                return ApiResponse::error(
                    'Payment amount exceeds current debt',
                    [
                        'amount' => $validated['amount'],
                        'current_debt' => $customer->current_debt,
                    ]
                );
            }

            // Record payment
            $transaction = $this->creditService->recordPayment(
                $customer,
                $validated['amount'],
                PaymentMethod::from($validated['payment_method']), // Cast to enum
                $validated['payment_reference'] ?? null,
                null,
                Auth::id(),
                $validated['notes'] ?? null
            );

            return ApiResponse::success(
                'Credit payment recorded successfully',
                [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $customer->id,
                    'amount_paid' => $validated['amount'],
                    'payment_method' => $validated['payment_method'],
                    'new_debt_balance' => $transaction->balance_after,
                    'payment_reference' => $validated['payment_reference'] ?? null,
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to record credit payment', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to record credit payment',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/credit-transactions/record-adjustment",
     *     summary="Record a credit adjustment for a customer",
     *     description="Record a manual adjustment to a customer's credit balance. Positive amounts increase debt, negative amounts decrease debt. Used for corrections or special circumstances.",
     *     operationId="recordCreditAdjustment",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "amount", "reason"},
     *             @OA\Property(property="customer_id", type="integer", example=5, description="ID of the customer"),
     *             @OA\Property(property="amount", type="number", format="float", example=-2000.00, description="Adjustment amount (positive increases debt, negative decreases debt)"),
     *             @OA\Property(property="reason", type="string", example="Correction", description="Reason for the adjustment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit adjustment recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit adjustment recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transaction_id", type="integer", example=9, description="ID of the created adjustment transaction"),
     *                 @OA\Property(property="customer_id", type="integer", example=4),
     *                 @OA\Property(property="adjustment_amount", type="number", format="float", example=10000, description="Absolute value of the adjustment amount"),
     *                 @OA\Property(property="new_debt_balance", type="string", example="246600.00", description="Customer's new outstanding debt balance"),
     *                 @OA\Property(property="reason", type="string", example="Adjustment for payment error", description="Reason provided for the adjustment")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T11:35:55.971860Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1554a0bf-efff-40c7-8422-0c5d99022980"),
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
     *         description="Customer not found"
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
     *                     property="customer_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The customer_id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount field must be a number.")
     *                 ),
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reason field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )*/
    public function recordAdjustment(RecordAdjustmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $customer = Customer::findOrFail($validated['customer_id']);

            // Record adjustment using service
            $transaction = $this->creditService->recordAdjustment(
                $customer,
                $validated['amount'],
                $validated['reason']
            );

            return ApiResponse::success(
                'Credit adjustment recorded successfully',
                [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $customer->id,
                    'adjustment_amount' => $validated['amount'],
                    'new_debt_balance' => $transaction->balance_after,
                    'reason' => $validated['reason'],
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to record credit adjustment', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to record credit adjustment',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/credit-transactions/record-write-off",
     *     summary="Record a credit write-off for a customer",
     *     description="Write off a portion or all of a customer's outstanding debt. This permanently reduces the customer's debt balance and is typically used for bad debts that are unlikely to be recovered.",
     *     operationId="recordCreditWriteOff",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "amount", "reason"},
     *             @OA\Property(property="customer_id", type="integer", example=5, description="ID of the customer"),
     *             @OA\Property(property="amount", type="number", format="float", example=-2000.00, description="Write-off amount (typically negative to reduce debt)"),
     *             @OA\Property(property="reason", type="string", example="Bad debt write-off - customer bankrupt", description="Reason for the write-off")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit write-off recorded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit write-off recorded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transaction_id", type="integer", example=10, description="ID of the created write-off transaction"),
     *                 @OA\Property(property="customer_id", type="integer", example=4),
     *                 @OA\Property(property="write_off_amount", type="number", format="float", example=100000, description="Absolute value of the amount written off"),
     *                 @OA\Property(property="new_debt_balance", type="string", example="146600.00", description="Customer's new outstanding debt balance after write-off"),
     *                 @OA\Property(property="reason", type="string", example="Bad debt write-off - customer bankrupt", description="Reason provided for the write-off")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T11:52:39.181758Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b373fcbe-3bd5-4be0-b62a-ccc0d49c3868"),
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
     *         description="Customer not found"
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
     *                     property="customer_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The customer_id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount field must be a number.")
     *                 ),
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="The reason field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )*/
    public function recordWriteOff(RecordAdjustmentRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $customer = Customer::findOrFail($validated['customer_id']);

            // Record adjustment using service
            $transaction = $this->creditService->writeOff(
                $customer,
                $validated['amount'],
                $validated['reason']
            );

            return ApiResponse::success(
                'Credit write-off recorded successfully',
                [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $customer->id,
                    'write_off_amount' => $validated['amount'],
                    'new_debt_balance' => $transaction->balance_after,
                    'reason' => $validated['reason'],
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to record credit write-off', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to record credit write-off',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/credit-transactions/analytics/overview",
     *     summary="Get credit analytics overview",
     *     description="Retrieve comprehensive analytics and statistics for the credit system including overall metrics, period-specific metrics, risk analysis, top debtors, and best payers. Supports optional date range filtering.",
     *     operationId="getCreditAnalyticsOverview",
     *     tags={"Credit Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Analytics start date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Analytics end date (Y-m-d format)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2026-01-31")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Credit analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overall_metrics",
     *                     type="object",
     *                     description="Overall credit system metrics across all time",
     *                     @OA\Property(property="total_credit_customers", type="integer", example=1, description="Total number of customers with credit facility"),
     *                     @OA\Property(property="active_credit_customers", type="integer", example=5, description="Number of active credit customers"),
     *                     @OA\Property(property="total_credit_issued", type="number", format="float", example=524988.68, description="Total credit issued to all customers"),
     *                     @OA\Property(property="total_payments_received", type="number", format="float", example=4999.84, description="Total payments received from customers"),
     *                     @OA\Property(property="total_outstanding_debt", type="number", format="float", example=296600, description="Total outstanding debt across all customers"),
     *                     @OA\Property(property="total_write_offs", type="number", format="float", example=0, description="Total amount written off"),
     *                     @OA\Property(property="collection_rate", type="number", format="float", example=0.95, description="Collection rate as a decimal (0-1)"),
     *                     @OA\Property(property="avg_debt_per_customer", type="number", format="float", example=59320, description="Average debt per customer")
     *                 ),
     *                 @OA\Property(
     *                     property="period_metrics",
     *                     type="object",
     *                     description="Metrics for the specified date range (or all time if no dates specified)",
     *                     @OA\Property(property="credit_sales", type="number", format="float", example=524988.68, description="Credit sales during the period"),
     *                     @OA\Property(property="payments_received", type="number", format="float", example=4999.84, description="Payments received during the period"),
     *                     @OA\Property(property="write_offs", type="number", format="float", example=0, description="Write-offs during the period"),
     *                     @OA\Property(property="new_credit_customers", type="integer", example=0, description="New credit customers added during the period"),
     *                     @OA\Property(property="customers_who_paid", type="integer", example=1, description="Number of customers who made payments during the period")
     *                 ),
     *                 @OA\Property(
     *                     property="risk_analysis",
     *                     type="object",
     *                     description="Risk categorization of credit customers",
     *                     @OA\Property(property="high_risk_customers", type="integer", example=0, description="Number of high-risk customers"),
     *                     @OA\Property(property="medium_risk_customers", type="integer", example=0, description="Number of medium-risk customers"),
     *                     @OA\Property(property="low_risk_customers", type="integer", example=5, description="Number of low-risk customers"),
     *                     @OA\Property(property="customers_over_limit", type="integer", example=0, description="Number of customers exceeding their credit limit")
     *                 ),
     *                 @OA\Property(
     *                     property="top_debtors",
     *                     type="array",
     *                     description="List of customers with the highest outstanding debt",
     *                     @OA\Items(
     *                         @OA\Property(property="customer_id", type="integer", example=4),
     *                         @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                         @OA\Property(property="current_debt", type="number", format="float", example=246600),
     *                         @OA\Property(property="credit_limit", type="number", format="float", example=500000),
     *                         @OA\Property(property="credit_utilization", type="number", format="float", example=49.32, description="Credit utilization percentage")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="best_payers",
     *                     type="array",
     *                     description="List of customers who have made the most payments",
     *                     @OA\Items(
     *                         @OA\Property(property="customer_id", type="integer", example=4),
     *                         @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                         @OA\Property(property="total_paid", type="number", format="float", example=4999.84)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T10:48:25.825230Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="20035a60-c092-460c-b374-d18f539cbf99"),
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
     *         response=422,
     *         description="Validation error - Invalid date format",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="date_from",
     *                     type="array",
     *                     @OA\Items(type="string", example="The date_from field must be a valid date.")
     *                 ),
     *                 @OA\Property(
     *                     property="date_to",
     *                     type="array",
     *                     @OA\Items(type="string", example="The date_to field must be a valid date.")
     *                 )
     *             )
     *         )
     *     )
     * )*/
    public function analytics(): JsonResponse
    {
        try {
            $dateFrom = request('date_from');
            $dateTo = request('date_to');

            $analytics = [
                'overall_metrics' => $this->creditService->getOverallMetrics(),
                'period_metrics' => $this->creditService->getPeriodMetrics($dateFrom, $dateTo),
                'risk_analysis' => $this->creditService->getRiskAnalysis(),
                'top_debtors' => $this->creditService->getTopDebtors(),
                'best_payers' => $this->creditService->getBestPayers($dateFrom, $dateTo),
            ];

            return ApiResponse::success(
                'Credit analytics retrieved successfully',
                $analytics,
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve credit analytics', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve credit analytics',
                ['error' => $e->getMessage()]
            );
        }
    }
}
