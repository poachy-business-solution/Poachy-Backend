<?php

namespace App\Http\Controllers\Api\Tenant\Drawer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Drawer\ListDrawerActivityRequest;
use App\Http\Requests\Tenant\Drawer\RecordCashMovementRequest;
use App\Http\Requests\Tenant\Drawer\RecordExpenseRequest;
use App\Http\Resources\Tenant\Drawer\CashMovementResource;
use App\Http\Resources\Tenant\Expense\ExpenseCategoryResource;
use App\Http\Resources\Tenant\Expense\ExpenseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Services\Tenant\Drawer\DrawerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Counter money outside sales: expenses recorded by anyone working the till
 * (owner approves staff ones) and cash put into / taken out of the drawer.
 */
class DrawerController extends Controller
{
    public function __construct(protected DrawerService $drawerService) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/drawer/expense-categories",
     *     summary="Expense types for the 3-tap expense picker",
     *     operationId="drawerExpenseCategories",
     *     tags={"Drawer"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Active expense categories")
     * )
     */
    public function categories(): JsonResponse
    {
        $categories = ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get();

        return ApiResponse::success('Expense types', ExpenseCategoryResource::collection($categories));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/drawer/expenses",
     *     summary="Record an expense from the counter",
     *     description="Three taps: what for (category), how much, paid from (till_cash | mpesa | own_pocket), optional receipt photo (multipart). Till-cash expenses attach to the recorder's till session and lower its expected cash. Staff expenses wait for approval; owners' and managers' are approved at once.",
     *     operationId="drawerRecordExpense",
     *     tags={"Drawer"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(
     *         required={"store_id","category_id","amount","paid_from"},
     *
     *         @OA\Property(property="store_id", type="integer"),
     *         @OA\Property(property="category_id", type="integer"),
     *         @OA\Property(property="amount", type="number"),
     *         @OA\Property(property="paid_from", type="string", enum={"till_cash","mpesa","own_pocket"}),
     *         @OA\Property(property="description", type="string"),
     *         @OA\Property(property="receipt", type="string", format="binary")
     *     ))),
     *
     *     @OA\Response(response=201, description="Expense recorded"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function recordExpense(RecordExpenseRequest $request): JsonResponse
    {
        $expense = $this->drawerService->recordExpense(
            $request->user(),
            $request->safe()->except('receipt'),
            $request->file('receipt'),
        );

        return ApiResponse::created('Expense saved', new ExpenseResource($expense));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/drawer/expenses",
     *     summary="Expenses recorded at the counter",
     *     description="Staff see the expenses they recorded; owners and managers see all. Filter by store, till session or local date range.",
     *     operationId="drawerListExpenses",
     *     tags={"Drawer"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Paginated expenses")
     * )
     */
    public function expenses(ListDrawerActivityRequest $request): JsonResponse
    {
        $user = $request->user();

        $expenses = Expense::query()
            ->when(! $user->worksAtEveryBranch(), fn (Builder $query) => $query->where('created_by', $user->id))
            ->when($request->validated('store_id'), fn (Builder $query, $storeId) => $query->where('store_id', $storeId))
            ->when($request->validated('shift_assignment_id'), fn (Builder $query, $id) => $query->where('shift_assignment_id', $id))
            ->when($request->validated('from'), fn (Builder $query, $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($request->validated('to'), fn (Builder $query, $to) => $query->whereDate('expense_date', '<=', $to))
            ->with(['category', 'store', 'creator'])
            ->latest()
            ->paginate((int) ($request->validated('per_page') ?? 20));

        return ApiResponse::paginated(ExpenseResource::collection($expenses), 'Expenses');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/drawer/cash-movements",
     *     summary="Record cash put into or taken out of the drawer",
     *     description="reason: float_top_up / other_in (in), owner_drawing / supplier_payment / other_out (out). Attaches to the recorder's till session and changes its expected cash. Only owners and managers record owner_drawing.",
     *     operationId="drawerRecordCashMovement",
     *     tags={"Drawer"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"store_id","reason","amount"},
     *
     *         @OA\Property(property="store_id", type="integer"),
     *         @OA\Property(property="reason", type="string", enum={"float_top_up","owner_drawing","supplier_payment","other_in","other_out"}),
     *         @OA\Property(property="amount", type="number"),
     *         @OA\Property(property="supplier_id", type="integer", nullable=true),
     *         @OA\Property(property="note", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=201, description="Recorded"),
     *     @OA\Response(response=403, description="Only owners and managers take cash home"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function recordCashMovement(RecordCashMovementRequest $request): JsonResponse
    {
        $movement = $this->drawerService->recordCashMovement($request->user(), $request->validated());

        return ApiResponse::created('Cash recorded', new CashMovementResource($movement));
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/drawer/cash-movements",
     *     summary="Cash put into or taken out of drawers",
     *     description="Staff see what they recorded; owners and managers see all.",
     *     operationId="drawerListCashMovements",
     *     tags={"Drawer"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Paginated cash movements")
     * )
     */
    public function cashMovements(ListDrawerActivityRequest $request): JsonResponse
    {
        $user = $request->user();

        $movements = CashMovement::query()
            ->when(! $user->worksAtEveryBranch(), fn (Builder $query) => $query->where('recorded_by', $user->id))
            ->when($request->validated('store_id'), fn (Builder $query, $storeId) => $query->where('store_id', $storeId))
            ->when($request->validated('shift_assignment_id'), fn (Builder $query, $id) => $query->where('shift_assignment_id', $id))
            ->when($request->validated('from'), fn (Builder $query, $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($request->validated('to'), fn (Builder $query, $to) => $query->whereDate('occurred_at', '<=', $to))
            ->with(['recorder', 'store', 'supplier'])
            ->latest('occurred_at')
            ->paginate((int) ($request->validated('per_page') ?? 20));

        return ApiResponse::paginated(CashMovementResource::collection($movements), 'Cash movements');
    }
}
