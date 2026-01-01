<?php

namespace App\Http\Controllers\Api\Tenant\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\ApproveExpenseRequest;
use App\Http\Requests\Tenant\Expense\RejectExpenseRequest;
use App\Http\Requests\Tenant\Expense\SetRecurrenceRequest;
use App\Http\Requests\Tenant\Expense\StoreExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateExpenseRequest;
use App\Http\Requests\Tenant\Expense\UploadReceiptRequest;
use App\Http\Resources\Tenant\Expense\ExpenseAnalyticsResource;
use App\Http\Resources\Tenant\Expense\ExpenseCollection;
use App\Http\Resources\Tenant\Expense\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Expenses\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        protected ExpenseService $service
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expenses",
     *     summary="Get paginated list of expenses",
     *     description="Retrieves a paginated list of all expenses for the current tenant with filtering options",
     *     operationId="getExpenses",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, example=15)
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter by expense category ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Parameter(
     *         name="supplier_id",
     *         in="query",
     *         description="Filter by supplier ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="payment_method",
     *         in="query",
     *         description="Filter by payment method",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"cash", "bank_transfer", "mpesa", "cheque", "card", "other"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="payment_status",
     *         in="query",
     *         description="Filter by payment status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "paid", "overdue"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="approval_status",
     *         in="query",
     *         description="Filter by approval status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"pending", "approved", "rejected"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter expenses from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter expenses to this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expenses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expenses retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                         @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="category_id", type="integer", example=6),
     *                         @OA\Property(property="amount", type="string", example="2500.00"),
     *                         @OA\Property(property="formatted_amount", type="string", example="KES 2,500.00"),
     *                         @OA\Property(property="description", type="string", example="Rent"),
     *                         @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                         @OA\Property(property="payment_method", type="string", example="cash"),
     *                         @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                         @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                         @OA\Property(property="payment_status", type="string", example="pending"),
     *                         @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                         @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                         @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                         @OA\Property(property="receipt_number", type="string", nullable=true, example=null),
     *                         @OA\Property(property="has_receipt", type="boolean", example=false),
     *                         @OA\Property(property="is_recurring", type="boolean", example=false),
     *                         @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                         @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                         @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                         @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                         @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                         @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                         @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approval_status", type="string", example="pending"),
     *                         @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                         @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example=null),
     *                         @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_editable", type="boolean", example=true),
     *                         @OA\Property(property="is_deletable", type="boolean", example=true),
     *                         @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                         @OA\Property(
     *                             property="category",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=6),
     *                             @OA\Property(property="name", type="string", example="Rent"),
     *                             @OA\Property(property="code", type="string", example="RENT"),
     *                             @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                             @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                             @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                             @OA\Property(property="requires_approval", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="display_order", type="integer", example=10),
     *                             @OA\Property(property="full_path", type="string", example="Rent"),
     *                             @OA\Property(property="level", type="integer", example=0),
     *                             @OA\Property(property="has_children", type="boolean", example=false),
     *                             @OA\Property(property="has_expenses", type="boolean", example=true),
     *                             @OA\Property(property="is_deletable", type="boolean", example=false),
     *                             @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                         ),
     *                         @OA\Property(
     *                             property="store",
     *                             type="object",
     *                             nullable=true,
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                             @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                             @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                             @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                             @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                             @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                             @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                             @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                             @OA\Property(property="is_main_store", type="boolean", example=true),
     *                             @OA\Property(property="is_active", type="boolean", example=true),
     *                             @OA\Property(property="status_label", type="string", example="Active"),
     *                             @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                             @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                         ),
     *                         @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                         @OA\Property(
     *                             property="creator",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="John Doe"),
     *                             @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                         ),
     *                         @OA\Property(property="approver", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_by", type="integer", example=1),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=2)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T12:36:21.402954Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ad2fa2b8-296f-4cf5-b7f0-e511b42ee5d8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'category_id',
                'store_id',
                'company_wide',
                'supplier_id',
                'approval_status',
                'payment_status',
                'payment_method',
                'start_date',
                'end_date',
                'is_recurring',
                'has_receipt',
                'search',
            ]);

            $perPage = $request->integer('per_page', 15);
            $expenses = $this->service->getPaginatedExpenses($filters, $perPage);

            return ApiResponse::paginated(
                new ExpenseCollection($expenses),
                'Expenses retrieved successfully.'
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve expenses.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses",
     *     summary="Create a new expense",
     *     description="Creates a new expense record for the current tenant",
     *     operationId="createExpense",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Expense data",
     *         @OA\JsonContent(
     *             required={"category_id", "amount", "description", "expense_date", "payment_method"},
     *             @OA\Property(
     *                 property="store_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the store where expense occurred. If null, expense is company-wide",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="category_id",
     *                 type="integer",
     *                 description="ID of the expense category (must be active)",
     *                 example=6
     *             ),
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 description="Expense amount (min: 0.01, max: 9999999999.99)",
     *                 example=2500.00,
     *                 minimum=0.01,
     *                 maximum=9999999999.99
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Detailed description of the expense (max: 5000 characters)",
     *                 example="Rent",
     *                 maxLength=5000
     *             ),
     *             @OA\Property(
     *                 property="expense_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date of expense (YYYY-MM-DD, must be today or earlier)",
     *                 example="2025-01-15"
     *             ),
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="string",
     *                 description="Payment method used",
     *                 enum={"cash", "bank_transfer", "mpesa", "cheque", "card", "other"},
     *                 example="cash"
     *             ),
     *             @OA\Property(
     *                 property="receipt_number",
     *                 type="string",
     *                 nullable=true,
     *                 description="Physical receipt number (max: 255 characters)",
     *                 example=null,
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="supplier_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the supplier if expense was paid to a supplier",
     *                 example=null
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Expense created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense created successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="2500.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 2,500.00"),
     *                 @OA\Property(property="description", type="string", example="Rent"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example=null),
     *                 @OA\Property(property="has_receipt", type="boolean", example=false),
     *                 @OA\Property(property="is_recurring", type="boolean", example=false),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example=null),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T12:30:07.828101Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="6d417ce2-f79b-44b3-b767-98833762b3c3"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The category id field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="array",
     *                     @OA\Items(type="string", example="The description field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="expense_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The expense date must be a date before or equal to today.")
     *                 ),
     *                 @OA\Property(
     *                     property="payment_method",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected payment method is invalid.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function store(StoreExpenseRequest $request): JsonResponse
    {
        try {
            $expense = $this->service->createExpense($request->validated());

            return ApiResponse::created(
                'Expense created successfully.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to create expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expenses/{id}",
     *     summary="Get a single expense by ID",
     *     description="Retrieves detailed information about a specific expense including related entities",
     *     operationId="getExpenseById",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000001"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=3),
     *                 @OA\Property(property="amount", type="string", example="2500.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 2,500.00"),
     *                 @OA\Property(property="description", type="string", example="Purchased printer paper and toner cartridges for office"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=false),
     *                 @OA\Property(property="is_recurring", type="boolean", example=false),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="approval_status", type="string", example="approved"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Approved"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example="2025-12-30T12:18:49.000000Z"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_editable", type="boolean", example=false),
     *                 @OA\Property(property="is_deletable", type="boolean", example=false),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Electricity"),
     *                     @OA\Property(property="code", type="string", example="ELECTRICITY"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly electricity bill"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=2),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=false),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=21),
     *                     @OA\Property(property="full_path", type="string", example="Utilities > Electricity"),
     *                     @OA\Property(property="level", type="integer", example=1),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="parent",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="name", type="string", example="Utilities"),
     *                         @OA\Property(property="code", type="string", example="UTILITIES"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Electricity, water, internet, etc."),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=false),
     *                         @OA\Property(property="display_order", type="integer", example=20),
     *                         @OA\Property(property="full_path", type="string", example="Utilities"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=true),
     *                         @OA\Property(property="has_expenses", type="boolean", example=false),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:20:22.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:55:39.000000Z")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:22:08.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="supplier",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="TechPro Manufacturing Ltd"),
     *                     @OA\Property(property="supplier_type", type="string", example="retailer"),
     *                     @OA\Property(property="supplier_type_display", type="string", example="Retailer"),
     *                     @OA\Property(property="supplier_type_description", type="string", example="Sells products directly to consumers"),
     *                     @OA\Property(property="contact_person", type="string", nullable=true, example="Mike Doe"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="mike.doe@techpro.com"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254712345678"),
     *                     @OA\Property(property="address", type="string", nullable=true, example="123 Industrial Area, Nairobi, Kenya"),
     *                     @OA\Property(property="registration_number", type="string", nullable=true, example="PVT-2023-001234"),
     *                     @OA\Property(property="credit_limit", type="string", example="1000000.00"),
     *                     @OA\Property(property="outstanding_balance", type="string", example="0.00"),
     *                     @OA\Property(property="payment_terms", type="string", example="net_30"),
     *                     @OA\Property(property="payment_terms_display", type="string", example="Net 30 Days"),
     *                     @OA\Property(property="payment_terms_description", type="string", example="Payment due within 30 days of invoice date"),
     *                     @OA\Property(property="payment_terms_days", type="integer", example=30),
     *                     @OA\Property(
     *                         property="bank_account_details",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="bank", type="string", example="Equity Bank Kenya"),
     *                         @OA\Property(property="branch", type="string", example="Industrial Area Branch"),
     *                         @OA\Property(property="account_name", type="string", example="TechPro Manufacturing Ltd"),
     *                         @OA\Property(property="account_number", type="string", example="0123456789")
     *                     ),
     *                     @OA\Property(property="rating", type="string", example="0.00"),
     *                     @OA\Property(property="total_orders", type="integer", example=0),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="notes", type="string", nullable=true, example="Specializes in electronic components and hardware manufacturing"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-19T12:33:47.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-19T12:35:43.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approver",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="parent_expense", type="object", nullable=true, example=null),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:18:49.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T12:18:49.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T12:37:59.293708Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3ef3d649-14af-43bb-af67-7b127033b911"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $expense = $this->service->getExpenseById($id);

            if (!$expense) {
                return ApiResponse::notFound('Expense not found.');
            }

            return ApiResponse::success(
                'Expense retrieved successfully.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/expenses/{id}",
     *     summary="Update an existing expense",
     *     description="Updates an expense record. Only pending or rejected expenses can be edited.",
     *     operationId="updateExpense",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         description="Expense update data. All fields are optional.",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="amount",
     *                 type="number",
     *                 format="float",
     *                 description="Expense amount (min: 0.01, max: 9999999999.99)",
     *                 example=25000.00,
     *                 minimum=0.01,
     *                 maximum=9999999999.99
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Detailed description of the expense (max: 5000 characters)",
     *                 example="Rent for January",
     *                 maxLength=5000
     *             ),
     *             @OA\Property(
     *                 property="expense_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date of expense (YYYY-MM-DD, must be today or earlier)",
     *                 example="2025-01-15"
     *             ),
     *             @OA\Property(
     *                 property="payment_method",
     *                 type="string",
     *                 description="Payment method used",
     *                 enum={"cash", "bank_transfer", "mpesa", "cheque", "card", "other"},
     *                 example="cash"
     *             ),
     *             @OA\Property(
     *                 property="payment_reference",
     *                 type="string",
     *                 nullable=true,
     *                 description="Payment transaction reference (max: 255 characters)",
     *                 example=null,
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="payment_status",
     *                 type="string",
     *                 nullable=true,
     *                 description="Payment status",
     *                 enum={"pending", "paid", "overdue"},
     *                 example="pending"
     *             ),
     *             @OA\Property(
     *                 property="receipt_number",
     *                 type="string",
     *                 nullable=true,
     *                 description="Physical receipt number (max: 255 characters)",
     *                 example="RCPT-789",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="supplier_id",
     *                 type="integer",
     *                 nullable=true,
     *                 description="ID of the supplier if expense was paid to a supplier",
     *                 example=null
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="25000.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                 @OA\Property(property="description", type="string", example="Rent for January"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=false),
     *                 @OA\Property(property="is_recurring", type="boolean", example=false),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example=null),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="approver", type="object", nullable=true, example=null),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T12:46:14.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T12:46:14.575325Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="aa31cbb5-ea3b-419e-85ec-76fb26110864"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="amount",
     *                     type="array",
     *                     @OA\Items(type="string", example="The amount must be at least 0.01.")
     *                 ),
     *                 @OA\Property(
     *                     property="expense_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The expense date must be a date before or equal to today.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions or expense cannot be edited",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function update(UpdateExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->service->updateExpense($id, $request->validated());

            return ApiResponse::success(
                'Expense updated successfully.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/expenses/{id}",
     *     summary="Delete an expense",
     *     description="Soft deletes an expense record. Only expenses that are editable and deletable can be removed. Approved expenses typically cannot be deleted.",
     *     operationId="deleteExpense",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense deleted successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T19:43:19.736882Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="84c7dce9-b8ff-430e-a608-902e7a9aa95c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions or expense cannot be deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete expense."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Cannot delete an approved expense.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteExpense($id);

            return ApiResponse::success('Expense deleted successfully.');
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/upload-receipt",
     *     summary="Upload a receipt for an expense",
     *     description="Uploads a receipt file (image or PDF) for an existing expense",
     *     operationId="uploadExpenseReceipt",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Receipt file upload",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="Receipt file (jpg, jpeg, png, or pdf). Max size: 2MB"
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Receipt uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Receipt uploaded successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:31:36.332491Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1c6bf77e-f3a7-4dea-ac4c-c0975959ebe7"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="file",
     *                     type="array",
     *                     @OA\Items(type="string", example="The file field is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function uploadReceipt(UploadReceiptRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->service->uploadReceipt(
                $id,
                $request->file('receipt')
            );

            return ApiResponse::success(
                'Receipt uploaded successfully.',
                // new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to upload receipt.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/expenses/{id}/delete-receipt",
     *     summary="Delete a receipt from an expense",
     *     description="Removes the uploaded receipt file from an existing expense",
     *     operationId="deleteExpenseReceipt",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Receipt deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Receipt deleted successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:38:40.053667Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="afcb67c8-312d-4f7e-bb9a-940ba8e139cd"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found or no receipt exists",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function deleteReceipt(int $id): JsonResponse
    {
        try {
            $expense = $this->service->deleteReceipt($id);

            return ApiResponse::success(
                'Receipt deleted successfully.',
                // new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to delete receipt.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/approve",
     *     summary="Approve a pending expense",
     *     description="Approves an expense that requires approval. Only pending expenses with uploaded receipts (if required) can be approved.",
     *     operationId="approveExpense",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense approved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense approved successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:41:12.656397Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d81c0e35-bdff-4cee-88f3-35c8f921f519"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Business logic error - Expense cannot be approved",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Failed to approve expense."),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="error", type="string", example="Only pending expenses can be approved.")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:17:01.859481Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="33088067-f793-4171-9049-0dd911ae0af1"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Failed to approve expense."),
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="error", type="string", example="Cannot approve: expense requires a receipt to be uploaded.")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:17:39.701103Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="028632e1-c781-40d4-9a74-781abb2f34cc"),
     *                         @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions to approve expenses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     )
     * )
     */
    public function approve(int $id): JsonResponse
    {
        try {
            $expense = $this->service->approveExpense(
                $id,
            );

            return ApiResponse::success(
                'Expense approved successfully.',
                // new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to approve expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/reject",
     *     summary="Reject a pending expense",
     *     description="Rejects an expense that requires approval. A rejection reason must be provided.",
     *     operationId="rejectExpense",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Rejection details",
     *         @OA\JsonContent(
     *             required={"reason"},
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 description="Reason for rejecting the expense (max: 5000 characters)",
     *                 example="Invalid receipt details",
     *                 maxLength=5000
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense rejected successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense rejected successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:22:57.668412Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="10314180-bf52-409e-b244-34b2ea116932"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="reason",
     *                     type="array",
     *                     @OA\Items(type="string", example="Rejection reason is required.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:20:25.278591Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a71a579b-45d7-49b6-bb5f-b35bd77c737d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions to reject expenses",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to reject expense."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="Only pending expenses can be rejected.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function reject(RejectExpenseRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->service->rejectExpense(
                $id,
                $request->input('reason')
            );

            return ApiResponse::success(
                'Expense rejected successfully.',
                // new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to reject expense.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expenses/pending-approval",
     *     summary="Get expenses pending approval",
     *     description="Retrieves all expenses that are currently pending approval",
     *     operationId="getPendingApprovalExpenses",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Pending approval expenses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Pending approval expenses retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=2),
     *                     @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="category_id", type="integer", example=6),
     *                     @OA\Property(property="amount", type="string", example="25000.00"),
     *                     @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                     @OA\Property(property="description", type="string", example="Rent for January"),
     *                     @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                     @OA\Property(property="payment_method", type="string", example="cash"),
     *                     @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                     @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                     @OA\Property(property="payment_status", type="string", example="pending"),
     *                     @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                     @OA\Property(property="receipt_path", type="string", nullable=true, example="receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                     @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                     @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                     @OA\Property(property="has_receipt", type="boolean", example=true),
     *                     @OA\Property(property="is_recurring", type="boolean", example=false),
     *                     @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                     @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                     @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                     @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approval_status", type="string", example="pending"),
     *                     @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example="2025-12-30T13:49:12.000000Z"),
     *                     @OA\Property(property="rejection_reason", type="string", nullable=true, example="Invalid receipt details"),
     *                     @OA\Property(property="is_editable", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=true),
     *                     @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="name", type="string", example="Rent"),
     *                         @OA\Property(property="code", type="string", example="RENT"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="display_order", type="integer", example=10),
     *                         @OA\Property(property="full_path", type="string", example="Rent"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=false),
     *                         @OA\Property(property="has_expenses", type="boolean", example=true),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                         @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                         @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                         @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="creator",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T13:49:12.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T13:56:37.764353Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="59b6cb30-1e62-4ab1-96e0-ad38918d8a8c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function pendingApproval(): JsonResponse
    {
        try {
            $expenses = $this->service->getPendingApproval();

            return ApiResponse::success(
                'Pending approval expenses retrieved successfully.',
                ExpenseResource::collection($expenses)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve pending expenses.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expenses/analytics",
     *     summary="Get expense analytics",
     *     description="Retrieves expense analytics grouped by category and payment method with filtering options",
     *     operationId="getExpenseAnalytics",
     *     tags={"Tenant - Expenses"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         description="Filter expenses from this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-01")
     *     ),
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         description="Filter expenses to this date (YYYY-MM-DD)",
     *         required=false,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense analytics retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense analytics retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="by_category",
     *                     type="array",
     *                     description="Expenses grouped by category",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="category_id", type="integer", example=3),
     *                         @OA\Property(property="category_name", type="string", example="Electricity"),
     *                         @OA\Property(property="category_code", type="string", example="ELECTRICITY"),
     *                         @OA\Property(property="expense_count", type="integer", description="Number of expenses in this category", example=1),
     *                         @OA\Property(property="total_amount", type="number", format="float", description="Total amount for this category", example=2500),
     *                         @OA\Property(property="formatted_amount", type="string", description="Formatted total amount", example="KES 2,500.00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="by_payment_method",
     *                     type="array",
     *                     description="Expenses grouped by payment method",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="payment_method", type="string", example="cash"),
     *                         @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                         @OA\Property(property="expense_count", type="integer", description="Number of expenses with this payment method", example=1),
     *                         @OA\Property(property="total_amount", type="number", format="float", description="Total amount for this payment method", example=2500),
     *                         @OA\Property(property="formatted_amount", type="string", description="Formatted total amount", example="KES 2,500.00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     description="Overall expense summary",
     *                     @OA\Property(property="total_amount", type="number", format="float", description="Total amount of all expenses", example=2500),
     *                     @OA\Property(property="formatted_total_amount", type="string", description="Formatted total amount", example="KES 2,500.00"),
     *                     @OA\Property(property="total_count", type="integer", description="Total number of expenses", example=1),
     *                     @OA\Property(property="average_expense", type="number", format="float", description="Average expense amount", example=2500),
     *                     @OA\Property(property="formatted_average_expense", type="string", description="Formatted average expense", example="KES 2,500.00")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T17:35:55.025332Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9ff60e50-6fa9-4ee0-baa0-e39c40e43ed9"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function analytics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'store_id']);
            $analytics = $this->service->getAnalytics($filters);

            return ApiResponse::success(
                'Expense analytics retrieved successfully.',
                new ExpenseAnalyticsResource($analytics)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve analytics.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/set-recurrence",
     *     summary="Set an expense as recurring",
     *     description="Configures an expense to be recurring with specified frequency and interval. Future expense instances will be auto-generated based on these settings.",
     *     operationId="setExpenseRecurrence",
     *     tags={"Tenant - Expenses - Recurring"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Recurrence configuration",
     *         @OA\JsonContent(
     *             required={"recurrence_frequency", "recurrence_interval", "recurrence_start_date"},
     *             @OA\Property(
     *                 property="recurrence_frequency",
     *                 type="string",
     *                 description="How often the expense recurs",
     *                 enum={"daily", "weekly", "monthly", "quarterly", "yearly"},
     *                 example="monthly"
     *             ),
     *             @OA\Property(
     *                 property="recurrence_interval",
     *                 type="integer",
     *                 description="Interval for recurrence (e.g., every 1 month, every 2 weeks). Min: 1, Max: 100",
     *                 example=1,
     *                 minimum=1,
     *                 maximum=100
     *             ),
     *             @OA\Property(
     *                 property="recurrence_start_date",
     *                 type="string",
     *                 format="date",
     *                 description="Date when recurring instances should start being generated (YYYY-MM-DD, must be today or later)",
     *                 example="2026-01-15"
     *             ),
     *             @OA\Property(
     *                 property="recurrence_end_date",
     *                 type="string",
     *                 format="date",
     *                 nullable=true,
     *                 description="Date when recurring should stop (YYYY-MM-DD). If null, recurrence continues indefinitely. Must be after start date.",
     *                 example="2026-12-15"
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Expense set as recurring successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Expense set as recurring successfully. Future instances will be auto-generated."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="25000.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                 @OA\Property(property="description", type="string", example="Rent for January"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example="receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=true),
     *                 @OA\Property(property="is_recurring", type="boolean", example=true, description="Now set to true"),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example="monthly"),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example="2026-01-15"),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example="2026-12-15"),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example="2026-02-15", description="Calculated next generation date"),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example="2025-12-30T13:49:12.000000Z"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example="Invalid receipt details"),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approver",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T16:22:15.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T16:22:15.043570Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cd3150c3-72fb-4221-8164-ba92362c3586"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="recurrence_frequency",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence frequency field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="recurrence_interval",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence interval must be at least 1.")
     *                 ),
     *                 @OA\Property(
     *                     property="recurrence_start_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence start date must be a date after or equal to today.")
     *                 ),
     *                 @OA\Property(
     *                     property="recurrence_end_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence end date must be a date after recurrence start date.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions or category not eligible for recurring",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred.")
     *         )
     *     )
     * )
     */
    public function setRecurrence(SetRecurrenceRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->service->setRecurrence($id, $request->validated());

            return ApiResponse::success(
                'Expense set as recurring successfully. Future instances will be auto-generated.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to set expense as recurring.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/expenses/{id}/recurrences",
     *     summary="Update recurrence settings",
     *     description="Updates recurrence configuration for an existing recurring expense. Changes apply to future instances only, not already generated instances.",
     *     operationId="updateExpenseRecurrence",
     *     tags={"Tenant - Expenses - Recurring"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID (must be a recurring expense)",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         description="Recurrence settings to update. All fields are optional.",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="recurrence_frequency",
     *                 type="string",
     *                 description="Update how often the expense recurs",
     *                 enum={"daily", "weekly", "monthly", "quarterly", "yearly"},
     *                 example="weekly"
     *             ),
     *             @OA\Property(
     *                 property="recurrence_interval",
     *                 type="integer",
     *                 description="Update interval for recurrence. Min: 1, Max: 100",
     *                 example=2,
     *                 minimum=1,
     *                 maximum=100
     *             ),
     *             @OA\Property(
     *                 property="recurrence_end_date",
     *                 type="string",
     *                 format="date",
     *                 nullable=true,
     *                 description="Update or set end date (YYYY-MM-DD). Must be after start date. Set to null for indefinite recurrence.",
     *                 example="2026-10-15"
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Recurrence settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurrence settings updated successfully. Changes apply to future instances only."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="25000.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                 @OA\Property(property="description", type="string", example="Rent for January"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example="receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=true),
     *                 @OA\Property(property="is_recurring", type="boolean", example=true),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example="monthly", description="Updated frequency if changed"),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1, description="Updated interval if changed"),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example="2026-01-15", description="Cannot be updated"),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example="2026-10-15", description="Updated end date"),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example="2026-03-15", description="Recalculated based on new settings"),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example="2025-12-30T13:49:12.000000Z"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example="Invalid receipt details"),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approver",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T17:56:42.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T17:56:42.985063Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="478ea01a-d9cf-4a26-9fab-8c134664ea85"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="recurrence_frequency",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected recurrence frequency is invalid.")
     *                 ),
     *                 @OA\Property(
     *                     property="recurrence_interval",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence interval must be at least 1.")
     *                 ),
     *                 @OA\Property(
     *                     property="recurrence_end_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The recurrence end date must be a date after recurrence start date.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update recurrence settings."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="This expense is not set as recurring.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function updateRecurrence(SetRecurrenceRequest $request, int $id): JsonResponse
    {
        try {
            $expense = $this->service->updateRecurrence($id, $request->validated());

            return ApiResponse::success(
                'Recurrence settings updated successfully. Changes apply to future instances only.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to update recurrence settings.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/cancel-recurrence",
     *     summary="Cancel recurring expense",
     *     description="Cancels the recurrence of an expense. Sets recurrence_end_date to yesterday and is_recurring to false. No more instances will be auto-generated.",
     *     operationId="cancelExpenseRecurrence",
     *     tags={"Tenant - Expenses - Recurring"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Expense ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Recurring expense cancelled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurring expense cancelled successfully. No more instances will be generated."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000002"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="25000.00"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                 @OA\Property(property="description", type="string", example="Rent for January"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2025-01-15"),
     *                 @OA\Property(property="payment_method", type="string", example="cash"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example="receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example="http://localhost/storage/receipts/2025/01/EXP-2025-000002_1767101967.jpg"),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example="RCPT-789"),
     *                 @OA\Property(property="has_receipt", type="boolean", example=true),
     *                 @OA\Property(property="is_recurring", type="boolean", example=false, description="Now set to false"),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example="monthly", description="Preserved for historical reference"),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example="2026-01-15"),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example="2025-12-29", description="Set to yesterday"),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null, description="Now null"),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=false),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approval_status", type="string", example="pending"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example="2025-12-30T13:49:12.000000Z"),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example="Invalid receipt details"),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(
     *                     property="approver",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T12:30:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T16:30:22.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T16:30:22.740726Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7c0ec73c-869d-46db-a70b-a627a67f66bc"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to cancel recurrence."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="This expense is not set as recurring.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function cancelRecurrence(int $id): JsonResponse
    {
        try {
            $expense = $this->service->cancelRecurrence($id);

            return ApiResponse::success(
                'Recurring expense cancelled successfully. No more instances will be generated.',
                new ExpenseResource($expense)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to cancel recurrence.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/expenses/{id}/generate-recurrence",
     *     summary="Manually generate next recurrence instance",
     *     description="Manually creates the next recurring expense instance based on parent expense settings. Updates parent's next_occurrence_date.",
     *     operationId="generateExpenseRecurrence",
     *     tags={"Tenant - Expenses - Recurring"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parent expense ID (must be a recurring expense)",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Recurrence instance generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurrence instance generated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="The newly created expense instance",
     *                 @OA\Property(property="id", type="integer", example=3, description="New expense ID"),
     *                 @OA\Property(property="expense_number", type="string", example="EXP-2025-000003", description="New expense number"),
     *                 @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                 @OA\Property(property="category_id", type="integer", example=6),
     *                 @OA\Property(property="amount", type="string", example="25000.00", description="Copied from parent"),
     *                 @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                 @OA\Property(property="description", type="string", example="Rent for January (Auto-generated)", description="Appended with '(Auto-generated)'"),
     *                 @OA\Property(property="expense_date", type="string", format="date", example="2026-02-15", description="Calculated based on recurrence settings"),
     *                 @OA\Property(property="payment_method", type="string", example="cash", description="Copied from parent"),
     *                 @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                 @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                 @OA\Property(property="payment_status", type="string", example="pending", description="Always pending for new instances"),
     *                 @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                 @OA\Property(property="receipt_path", type="string", nullable=true, example=null, description="Receipt not copied"),
     *                 @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="receipt_number", type="string", nullable=true, example=null),
     *                 @OA\Property(property="has_receipt", type="boolean", example=false),
     *                 @OA\Property(property="is_recurring", type="boolean", example=false, description="Instance is not recurring itself"),
     *                 @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                 @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                 @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                 @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=2, description="Links to parent expense"),
     *                 @OA\Property(property="is_recurrence_instance", type="boolean", example=true, description="Marked as instance"),
     *                 @OA\Property(property="supplier_id", type="integer", nullable=true, example=null, description="Copied from parent"),
     *                 @OA\Property(property="approval_status", type="string", example="pending", description="Always pending for new instances"),
     *                 @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                 @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example=null),
     *                 @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                 @OA\Property(property="is_editable", type="boolean", example=true),
     *                 @OA\Property(property="is_deletable", type="boolean", example=true),
     *                 @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                 @OA\Property(
     *                     property="category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=6),
     *                     @OA\Property(property="name", type="string", example="Rent"),
     *                     @OA\Property(property="code", type="string", example="RENT"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                     @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                     @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                     @OA\Property(property="requires_approval", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="display_order", type="integer", example=10),
     *                     @OA\Property(property="full_path", type="string", example="Rent"),
     *                     @OA\Property(property="level", type="integer", example=0),
     *                     @OA\Property(property="has_children", type="boolean", example=false),
     *                     @OA\Property(property="has_expenses", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=false),
     *                     @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                 ),
     *                 @OA\Property(
     *                     property="store",
     *                     type="object",
     *                     nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                     @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                     @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                     @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                     @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                     @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                     @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                     @OA\Property(property="is_main_store", type="boolean", example=true),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="status_label", type="string", example="Active"),
     *                     @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                 ),
     *                 @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="creator",
     *                     type="object",
     *                     description="User who triggered the generation",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                 ),
     *                 @OA\Property(property="created_by", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T16:34:40.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T16:34:40.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T16:34:40.137789Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="d9e14818-8e06-49ea-a1bd-4c8faa3122d2"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate recurrence instance."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="This expense is not set as recurring.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */


    public function generateRecurrence(int $id): JsonResponse
    {
        try {
            $parentExpense = $this->service->getExpenseById($id);

            if (!$parentExpense) {
                return ApiResponse::notFound('Expense not found.');
            }

            $newInstance = $this->service->generateRecurrenceInstance($parentExpense);

            return ApiResponse::created(
                'Recurrence instance generated successfully.',
                new ExpenseResource($newInstance)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to generate recurrence instance.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/expenses/{id}/recurrences",
     *     summary="Get all recurrence instances of a parent expense",
     *     description="Retrieves all auto-generated expense instances that were created from a recurring parent expense",
     *     operationId="getExpenseRecurrences",
     *     tags={"Tenant - Expenses - Recurring"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Parent expense ID (must be a recurring expense)",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Recurrence instances retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Recurrence instances retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of all generated expense instances",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="expense_number", type="string", example="EXP-2025-000003"),
     *                     @OA\Property(property="store_id", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="category_id", type="integer", example=6),
     *                     @OA\Property(property="amount", type="string", example="25000.00"),
     *                     @OA\Property(property="formatted_amount", type="string", example="KES 25,000.00"),
     *                     @OA\Property(property="description", type="string", example="Rent for January (Auto-generated)"),
     *                     @OA\Property(property="expense_date", type="string", format="date", example="2026-02-15"),
     *                     @OA\Property(property="payment_method", type="string", example="cash"),
     *                     @OA\Property(property="payment_method_label", type="string", example="Cash"),
     *                     @OA\Property(property="payment_reference", type="string", nullable=true, example=null),
     *                     @OA\Property(property="payment_status", type="string", example="pending"),
     *                     @OA\Property(property="payment_status_label", type="string", example="Pending"),
     *                     @OA\Property(property="receipt_path", type="string", nullable=true, example=null),
     *                     @OA\Property(property="receipt_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="receipt_number", type="string", nullable=true, example=null),
     *                     @OA\Property(property="has_receipt", type="boolean", example=false),
     *                     @OA\Property(property="is_recurring", type="boolean", example=false),
     *                     @OA\Property(property="recurrence_frequency", type="string", nullable=true, example=null),
     *                     @OA\Property(property="recurrence_interval", type="integer", example=1),
     *                     @OA\Property(property="recurrence_start_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="recurrence_end_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="next_occurrence_date", type="string", nullable=true, format="date", example=null),
     *                     @OA\Property(property="parent_expense_id", type="integer", nullable=true, example=2, description="Links back to parent"),
     *                     @OA\Property(property="is_recurrence_instance", type="boolean", example=true, description="Always true for instances"),
     *                     @OA\Property(property="supplier_id", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approval_status", type="string", example="pending"),
     *                     @OA\Property(property="approval_status_label", type="string", example="Pending Approval"),
     *                     @OA\Property(property="approved_by", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="approved_at", type="string", nullable=true, format="date-time", example=null),
     *                     @OA\Property(property="rejection_reason", type="string", nullable=true, example=null),
     *                     @OA\Property(property="is_editable", type="boolean", example=true),
     *                     @OA\Property(property="is_deletable", type="boolean", example=true),
     *                     @OA\Property(property="can_be_approved", type="boolean", example=false),
     *                     @OA\Property(
     *                         property="category",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="name", type="string", example="Rent"),
     *                         @OA\Property(property="code", type="string", example="RENT"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Monthly store or office rent payments"),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="is_recurring_eligible", type="boolean", example=true),
     *                         @OA\Property(property="requires_receipt", type="boolean", example=true),
     *                         @OA\Property(property="requires_approval", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="display_order", type="integer", example=10),
     *                         @OA\Property(property="full_path", type="string", example="Rent"),
     *                         @OA\Property(property="level", type="integer", example=0),
     *                         @OA\Property(property="has_children", type="boolean", example=false),
     *                         @OA\Property(property="has_expenses", type="boolean", example=true),
     *                         @OA\Property(property="is_deletable", type="boolean", example=false),
     *                         @OA\Property(property="parent", type="object", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-29T07:49:04.000000Z")
     *                     ),
     *                     @OA\Property(
     *                         property="store",
     *                         type="object",
     *                         nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="STR-2025-74622"),
     *                         @OA\Property(property="name", type="string", example="Branch Store - Mombasa"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Mombasa branch location"),
     *                         @OA\Property(property="address", type="string", example="Gedi, Kwale"),
     *                         @OA\Property(property="city", type="string", nullable=true, example="Mombasa"),
     *                         @OA\Property(property="region", type="string", nullable=true, example="Coast"),
     *                         @OA\Property(property="phone", type="string", nullable=true, example="+254723456789"),
     *                         @OA\Property(property="email", type="string", nullable=true, example="info@store.com"),
     *                         @OA\Property(property="is_main_store", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status_label", type="string", example="Active"),
     *                         @OA\Property(property="store_type_label", type="string", example="Main Store"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-14T19:48:13.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-15T18:15:20.000000Z")
     *                     ),
     *                     @OA\Property(property="supplier", type="object", nullable=true, example=null),
     *                     @OA\Property(
     *                         property="creator",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@techhaven.com")
     *                     ),
     *                     @OA\Property(property="created_by", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-30T16:34:40.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-30T16:34:40.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-30T16:40:00.000000Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a1b2c3d4-e5f6-7890-abcd-ef1234567890"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Expense not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized.")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error or business logic error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve recurrence instances."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="error", type="string", example="This expense is not set as recurring.")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function getRecurrences(int $id): JsonResponse
    {
        try {
            $recurrences = $this->service->getRecurrenceInstances($id);

            return ApiResponse::success(
                'Recurrence instances retrieved successfully.',
                ExpenseResource::collection($recurrences)
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to retrieve recurrences.',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
