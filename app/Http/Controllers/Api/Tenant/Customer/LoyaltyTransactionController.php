<?php

namespace App\Http\Controllers\Api\Tenant\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Customer\AwardPointsRequest;
use App\Http\Requests\Tenant\Customer\ListLoyaltyTransactionsRequest;
use App\Http\Resources\Tenant\Customer\LoyaltyTransactionResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Customer;
use App\Models\Tenant\LoyaltyTransaction;
use App\Services\Tenant\Sales\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LoyaltyTransactionController extends Controller
{
    public function __construct(
        protected LoyaltyService $loyaltyService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/loyalty-transactions",
     *     summary="Get list of loyalty transactions",
     *     description="Retrieve a paginated list of loyalty transactions with optional filters and sorting. Returns transaction details along with summary statistics.",
     *     operationId="getLoyaltyTransactions",
     *     tags={"Loyalty Transactions"},
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
     *         description="Filter by transaction type (earned, redeemed, expired, adjustment)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"earned", "redeemed", "expired", "adjustment"})
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
     *         name="expiring_within_days",
     *         in="query",
     *         description="Filter points expiring within specified days",
     *         required=false,
     *         @OA\Schema(type="integer", example=30)
     *     ),
     *     @OA\Parameter(
     *         name="reference_type",
     *         in="query",
     *         description="Filter by reference type (e.g., App\Models\Tenant\Sale)",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="include_expired",
     *         in="query",
     *         description="Include expired points (default: false)",
     *         required=false,
     *         @OA\Schema(type="boolean", example=false)
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
     *         description="Sort field (created_at, points, balance_after)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "points", "balance_after"}, example="created_at")
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
     *         description="Loyalty transactions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Loyalty transactions retrieved successfully"),
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
     *                             @OA\Property(property="id", type="integer", example=22),
     *                             @OA\Property(property="customer_id", type="integer", example=4),
     *                             @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                             @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                             @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                             @OA\Property(property="transaction_type", type="string", example="earned"),
     *                             @OA\Property(property="points", type="number", format="float", example=1500),
     *                             @OA\Property(property="balance_after", type="number", format="float", example=63742),
     *                             @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                             @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                             @OA\Property(
     *                                 property="reference",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="type", type="string", example="sale"),
     *                                 @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                                 @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                                 @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09")
     *                             ),
     *                             @OA\Property(property="description", type="string", example="Points earned from sale INV-STR-2025-74622-2026-01-000009"),
     *                             @OA\Property(property="expires_at", type="string", format="date", nullable=true, example="2027-01-09"),
     *                             @OA\Property(property="is_expired", type="boolean", example=false),
     *                             @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=363),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="links",
     *                         type="object",
     *                         @OA\Property(property="first", type="string", example="http://techhaven.localhost/api/v1/tenant/loyalty-transactions?page=1"),
     *                         @OA\Property(property="last", type="string", example="http://techhaven.localhost/api/v1/tenant/loyalty-transactions?page=2"),
     *                         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                         @OA\Property(property="next", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/loyalty-transactions?page=2")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="from", type="integer", example=1),
     *                         @OA\Property(property="last_page", type="integer", example=2),
     *                         @OA\Property(
     *                             property="links",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="url", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/loyalty-transactions?page=1"),
     *                                 @OA\Property(property="label", type="string", example="1"),
     *                                 @OA\Property(property="page", type="integer", nullable=true, example=1),
     *                                 @OA\Property(property="active", type="boolean", example=true)
     *                             )
     *                         ),
     *                         @OA\Property(property="path", type="string", example="http://techhaven.localhost/api/v1/tenant/loyalty-transactions"),
     *                         @OA\Property(property="per_page", type="integer", example=15),
     *                         @OA\Property(property="to", type="integer", example=15),
     *                         @OA\Property(property="total", type="integer", example=22)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_points_earned", type="number", format="float", example=34304.5),
     *                     @OA\Property(property="total_points_redeemed", type="number", format="float", example=5000),
     *                     @OA\Property(property="total_points_expired", type="number", format="float", example=0),
     *                     @OA\Property(property="net_points_outstanding", type="number", format="float", example=29304.5),
     *                     @OA\Property(property="unique_customers", type="integer", example=1),
     *                     @OA\Property(property="avg_points_per_customer", type="number", format="float", example=29304.5),
     *                     @OA\Property(property="redemption_rate", type="number", format="float", example=14.58),
     *                     @OA\Property(property="points_expiring_soon", type="number", format="float", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T07:22:04.296735Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="5af88968-df09-4990-b768-dcabd87a77f5"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User does not have the right permissions."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T07:27:27.579238Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="637d226c-779a-4f1a-8138-86a43d6921e8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )*/
    public function index(ListLoyaltyTransactionsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            // Build query with filters
            $query = LoyaltyTransaction::query()
                ->with(['customer:id,name,phone,email,loyalty_points', 'reference'])
                ->select('loyalty_transactions.*');

            // Apply filters
            if (!empty($validated['customer_id'])) {
                $query->where('customer_id', $validated['customer_id']);
            }

            if (!empty($validated['transaction_type'])) {
                $query->where('transaction_type', $validated['transaction_type']);
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

            if (isset($validated['expiring_within_days'])) {
                $query->expiringSoon($validated['expiring_within_days']);
            }

            // Exclude expired by default unless explicitly requested
            if (empty($validated['include_expired'])) {
                $query->active();
            }

            // Sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($validated['per_page'] ?? 15, 100);
            $transactions = $query->paginate($perPage);

            // Calculate analytics summary
            $summary = $this->loyaltyService->calculateSummary($validated);

            return ApiResponse::success(
                'Loyalty transactions retrieved successfully',
                [
                    'transactions' => LoyaltyTransactionResource::collection($transactions)->response()->getData(),
                    'summary' => $summary,
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve loyalty transactions', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve loyalty transactions',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/loyalty-transactions/{id}",
     *     summary="Get a specific loyalty transaction",
     *     description="Retrieve detailed information about a single loyalty transaction by its ID. The reference object structure varies based on whether it's a sale reference or manual award.",
     *     operationId="getLoyaltyTransactionById",
     *     tags={"Loyalty Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Loyalty transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=22)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loyalty transaction retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Loyalty transaction retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=22),
     *                 @OA\Property(property="customer_id", type="integer", example=4),
     *                 @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                 @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                 @OA\Property(property="transaction_type", type="string", example="earned"),
     *                 @OA\Property(property="points", type="number", format="float", example=1500),
     *                 @OA\Property(property="balance_after", type="number", format="float", example=63742),
     *                 @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                 @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                 @OA\Property(
     *                     property="reference",
     *                     type="object",
     *                     nullable=true,
     *                     description="Reference object structure varies: for sales it contains type, sale_number, total_amount, sale_date; for manual awards it contains type, awarded_by, awarded_by_id",
     *                     oneOf={
     *                         @OA\Schema(
     *                             type="object",
     *                             description="Sale reference",
     *                             @OA\Property(property="type", type="string", example="sale"),
     *                             @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                             @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                             @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09")
     *                         ),
     *                         @OA\Schema(
     *                             type="object",
     *                             description="Manual award reference",
     *                             @OA\Property(property="type", type="string", example="Manual Award"),
     *                             @OA\Property(property="awarded_by", type="string", example="John Doe"),
     *                             @OA\Property(property="awarded_by_id", type="integer", example=1)
     *                         )
     *                     }
     *                 ),
     *                 @OA\Property(property="description", type="string", example="Points earned from sale INV-STR-2025-74622-2026-01-000009"),
     *                 @OA\Property(property="expires_at", type="string", format="date", nullable=true, example="2027-01-09"),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=363),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T07:26:52.248310Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8432b375-9639-4b2f-9599-fb7f1c619fe4"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have the right permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User does not have the right permissions."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T07:27:27.579238Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="637d226c-779a-4f1a-8138-86a43d6921e8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Loyalty transaction not found"
     *     )
     * )*/
    public function show(int $id): JsonResponse
    {
        try {
            $transaction = LoyaltyTransaction::with(['customer', 'reference'])
                ->findOrFail($id);

            return ApiResponse::success(
                'Loyalty transaction retrieved successfully',
                new LoyaltyTransactionResource($transaction)
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Loyalty transaction not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve loyalty transaction', [
                'tenant_id' => tenant()->id,
                'transaction_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve loyalty transaction',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/customers/{customer_id}/loyalty-transactions",
     *     summary="Get loyalty transactions for a specific customer",
     *     description="Retrieve paginated loyalty transaction history for a specific customer along with their current balance and summary statistics.",
     *     operationId="getCustomerLoyaltyTransactions",
     *     tags={"Loyalty Transactions"},
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
     *         description="Customer loyalty history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Customer loyalty history retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="customer",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="phone", type="string", example="+254712345602"),
     *                     @OA\Property(property="current_balance", type="string", example="65408.50")
     *                 ),
     *                 @OA\Property(
     *                     property="transactions",
     *                     type="object",
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=22),
     *                             @OA\Property(property="customer_id", type="integer", example=4),
     *                             @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                             @OA\Property(property="customer_phone", type="string", example="+254712345602"),
     *                             @OA\Property(property="customer_email", type="string", example="jane.smith@example.com"),
     *                             @OA\Property(property="transaction_type", type="string", example="earned"),
     *                             @OA\Property(property="points", type="number", format="float", example=1500),
     *                             @OA\Property(property="balance_after", type="number", format="float", example=63742),
     *                             @OA\Property(property="reference_type", type="string", nullable=true, example="App\\Models\\Tenant\\Sale"),
     *                             @OA\Property(property="reference_id", type="integer", nullable=true, example=9),
     *                             @OA\Property(
     *                                 property="reference",
     *                                 type="object",
     *                                 nullable=true,
     *                                 @OA\Property(property="type", type="string", example="sale"),
     *                                 @OA\Property(property="sale_number", type="string", example="INV-STR-2025-74622-2026-01-000009"),
     *                                 @OA\Property(property="total_amount", type="number", format="float", example=166650),
     *                                 @OA\Property(property="sale_date", type="string", format="date", example="2026-01-09")
     *                             ),
     *                             @OA\Property(property="description", type="string", example="Points earned from sale INV-STR-2025-74622-2026-01-000009"),
     *                             @OA\Property(property="expires_at", type="string", format="date", nullable=true, example="2027-01-09"),
     *                             @OA\Property(property="is_expired", type="boolean", example=false),
     *                             @OA\Property(property="days_until_expiry", type="integer", nullable=true, example=363),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-09T19:38:57+03:00")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="links",
     *                         type="object",
     *                         @OA\Property(property="first", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/loyalty-transactions?page=1"),
     *                         @OA\Property(property="last", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/loyalty-transactions?page=2"),
     *                         @OA\Property(property="prev", type="string", nullable=true, example=null),
     *                         @OA\Property(property="next", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/customers/4/loyalty-transactions?page=2")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer", example=1),
     *                         @OA\Property(property="from", type="integer", example=1),
     *                         @OA\Property(property="last_page", type="integer", example=2),
     *                         @OA\Property(
     *                             property="links",
     *                             type="array",
     *                             @OA\Items(
     *                                 @OA\Property(property="url", type="string", nullable=true, example="http://techhaven.localhost/api/v1/tenant/customers/4/loyalty-transactions?page=1"),
     *                                 @OA\Property(property="label", type="string", example="1"),
     *                                 @OA\Property(property="page", type="integer", nullable=true, example=1),
     *                                 @OA\Property(property="active", type="boolean", example=true)
     *                             )
     *                         ),
     *                         @OA\Property(property="path", type="string", example="http://techhaven.localhost/api/v1/tenant/customers/4/loyalty-transactions"),
     *                         @OA\Property(property="per_page", type="integer", example=20),
     *                         @OA\Property(property="to", type="integer", example=20),
     *                         @OA\Property(property="total", type="integer", example=22)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="balance_summary",
     *                     type="object",
     *                     @OA\Property(property="total_earned", type="number", format="float", example=34304.5),
     *                     @OA\Property(property="total_redeemed", type="number", format="float", example=5000),
     *                     @OA\Property(property="total_expired", type="number", format="float", example=0),
     *                     @OA\Property(property="current_balance", type="number", format="float", example=29304.5),
     *                     @OA\Property(property="points_expiring_soon", type="number", format="float", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T07:31:25.657925Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="0a5a5c13-a531-415b-815b-a88b51eddcc5"),
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

            $transactions = LoyaltyTransaction::byCustomer($customerId)
                ->with('reference')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Calculate balance summary
            $balanceSummary = $this->loyaltyService->calculateCustomerBalanceSummary($customerId);

            return ApiResponse::success(
                'Customer loyalty history retrieved successfully',
                [
                    'customer' => [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'current_balance' => $customer->loyalty_points,
                    ],
                    'transactions' => LoyaltyTransactionResource::collection($transactions)->response()->getData(),
                    'balance_summary' => $balanceSummary,
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve customer loyalty history', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve customer loyalty history',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/loyalty-transactions/award-manual",
     *     summary="Award manual loyalty points to a customer",
     *     description="Manually award loyalty points to a customer with a specified reason. This creates a loyalty transaction record and updates the customer's balance.",
     *     operationId="awardManualLoyaltyPoints",
     *     tags={"Loyalty Transactions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id", "points", "reason"},
     *             @OA\Property(property="customer_id", type="integer", example=5, description="ID of the customer to award points to"),
     *             @OA\Property(property="points", type="number", format="float", example=100.00, description="Points to award"),
     *             @OA\Property(property="reason", type="string", example="Promotional bonus for January 2025", description="Reason for manual award")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Loyalty points awarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Loyalty points awarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transaction_id", type="integer", example=25),
     *                 @OA\Property(property="customer_id", type="integer", example=2),
     *                 @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                 @OA\Property(property="customer_phone", type="string", example="+254712345679"),
     *                 @OA\Property(property="points_awarded", type="number", format="float", example=2500),
     *                 @OA\Property(property="new_balance", type="string", example="7500.00"),
     *                 @OA\Property(property="reason", type="string", example="Promotional bonus for December 2025")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T08:04:36.570926Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6f68f676-2f15-4a53-bc9c-8bb2754dff91"),
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
     *                     property="points",
     *                     type="array",
     *                     @OA\Items(type="string", example="The points field must be a number.")
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
    public function awardManual(AwardPointsRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $customer = Customer::findOrFail($validated['customer_id']);

            $transaction = $this->loyaltyService->awardPoints(
                $customer,
                $validated['points'],
                null,                    // referenceType
                Auth::id(),              // referenceId
                $validated['reason']     // description
            );

            return ApiResponse::success(
                'Loyalty points awarded successfully',
                [
                    'transaction_id' => $transaction->id,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'points_awarded' => $validated['points'],
                    'new_balance' => $transaction->balance_after,
                    'reason' => $validated['reason'],
                ]
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ApiResponse::error('Customer not found', 404);
        } catch (\Exception $e) {
            Log::error('Failed to award loyalty points', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Failed to award loyalty points',
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/loyalty-transactions/analytics/overview",
     *     summary="Get loyalty program analytics overview",
     *     description="Retrieve comprehensive analytics and statistics for the loyalty program including overall metrics, period-specific metrics, expiry analysis, and top performers. Supports optional date range filtering.",
     *     operationId="getLoyaltyAnalyticsOverview",
     *     tags={"Loyalty Transactions"},
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
     *         description="Loyalty analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Loyalty analytics retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="overall_metrics",
     *                     type="object",
     *                     description="Overall loyalty program metrics across all time",
     *                     @OA\Property(property="total_members", type="integer", example=11, description="Total number of loyalty program members"),
     *                     @OA\Property(property="active_members", type="integer", example=11, description="Number of active members"),
     *                     @OA\Property(property="total_points_issued", type="number", format="float", example=39304.5, description="Total points issued to all members"),
     *                     @OA\Property(property="total_points_redeemed", type="number", format="float", example=5000, description="Total points redeemed by members"),
     *                     @OA\Property(property="total_points_expired", type="number", format="float", example=0, description="Total points that have expired"),
     *                     @OA\Property(property="outstanding_points", type="number", format="float", example=75428.5, description="Total points outstanding across all members"),
     *                     @OA\Property(property="redemption_rate", type="number", format="float", example=12.72, description="Redemption rate as a percentage"),
     *                     @OA\Property(property="avg_points_per_member", type="number", format="float", example=6857.14, description="Average points per member")
     *                 ),
     *                 @OA\Property(
     *                     property="period_metrics",
     *                     type="object",
     *                     description="Metrics for the specified date range (or all time if no dates specified)",
     *                     @OA\Property(property="points_earned", type="number", format="float", example=39304.5, description="Points earned during the period"),
     *                     @OA\Property(property="points_redeemed", type="number", format="float", example=5000, description="Points redeemed during the period"),
     *                     @OA\Property(property="points_expired", type="number", format="float", example=0, description="Points expired during the period"),
     *                     @OA\Property(property="new_members", type="integer", example=0, description="New members joined during the period"),
     *                     @OA\Property(property="active_redeemers", type="integer", example=1, description="Number of members who redeemed points during the period")
     *                 ),
     *                 @OA\Property(
     *                     property="expiry_analysis",
     *                     type="object",
     *                     description="Analysis of points expiring in various time windows",
     *                     @OA\Property(property="expiring_within_7_days", type="number", format="float", example=0, description="Points expiring within 7 days"),
     *                     @OA\Property(property="expiring_within_30_days", type="number", format="float", example=0, description="Points expiring within 30 days"),
     *                     @OA\Property(property="expiring_within_90_days", type="number", format="float", example=0, description="Points expiring within 90 days")
     *                 ),
     *                 @OA\Property(
     *                     property="top_earners",
     *                     type="array",
     *                     description="List of customers who earned the most points",
     *                     @OA\Items(
     *                         @OA\Property(property="customer_id", type="integer", example=4),
     *                         @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="points_earned", type="number", format="float", example=34304.5)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="top_redeemers",
     *                     type="array",
     *                     description="List of customers who redeemed the most points",
     *                     @OA\Items(
     *                         @OA\Property(property="customer_id", type="integer", example=4),
     *                         @OA\Property(property="customer_name", type="string", example="Jane Smith"),
     *                         @OA\Property(property="points_redeemed", type="number", format="float", example=5000)
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-10T08:20:29.442145Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="53ef3591-a553-4b55-a365-ddd147d7dd5a"),
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
                'overall_metrics' => $this->loyaltyService->getOverallMetrics(),
                'period_metrics' => $this->loyaltyService->getPeriodMetrics($dateFrom, $dateTo),
                'expiry_analysis' => $this->loyaltyService->getExpiryAnalysis(),
                'top_earners' => $this->loyaltyService->getTopEarners($dateFrom, $dateTo),
                'top_redeemers' => $this->loyaltyService->getTopRedeemers($dateFrom, $dateTo),
            ];

            return ApiResponse::success(
                'Loyalty analytics retrieved successfully',
                $analytics
            );
        } catch (\Exception $e) {
            Log::error('Failed to retrieve loyalty analytics', [
                'tenant_id' => tenant()->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Failed to retrieve loyalty analytics',
                ['error' => $e->getMessage()]
            );
        }
    }
}
