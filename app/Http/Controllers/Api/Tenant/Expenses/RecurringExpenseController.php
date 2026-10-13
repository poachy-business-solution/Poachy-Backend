<?php

namespace App\Http\Controllers\Api\Tenant\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Expense\RecurringExpenseRequest;
use App\Http\Requests\Tenant\Expense\UpdateRecurringExpenseRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Tenant\Expense;
use App\Services\Tenant\Expenses\RecurringExpenseService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Repeating costs (rent, salaries, power…) for owners and managers. Each due date is recorded
 * automatically by `expenses:record-recurring` (daily, early morning).
 */
class RecurringExpenseController extends Controller
{
    public function __construct(private RecurringExpenseService $service, private TillService $till) {}

    /**
     * @OA\Get(path="/api/v1/tenant/recurring-expenses", summary="Repeating costs, with the monthly total of the active ones", operationId="tenantRecurringExpenses",
     *     tags={"Expenses"}, security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="store_id", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="data.items[] (id, category, amount, frequency, start_date, end_date, next_date, status active|ended, per_month); data.monthly_total"),
     *     @OA\Response(response=403, description="Not owner/manager")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $today = $this->today();
        $items = Expense::query()
            ->where('is_recurring', true)
            ->whereNull('parent_expense_id')
            ->when($request->integer('store_id'), fn ($q, $id) => $q->where('store_id', $id))
            ->with(['category:id,name', 'store:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Expense $e) => $this->present($e, $today));

        return ApiResponse::success('Repeating costs', [
            'items' => $items->values(),
            'monthly_total' => round($items->where('status', 'active')->sum('per_month'), 2),
        ]);
    }

    /**
     * @OA\Post(path="/api/v1/tenant/recurring-expenses", summary="Add a repeating cost", operationId="tenantRecurringExpenseStore",
     *     tags={"Expenses"}, security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"store_id","category_id","amount","frequency","start_date"},
     *
     *         @OA\Property(property="store_id", type="integer"), @OA\Property(property="category_id", type="integer"), @OA\Property(property="amount", type="number"),
     *         @OA\Property(property="frequency", type="string", enum={"daily","weekly","monthly"}), @OA\Property(property="start_date", type="string", format="date"),
     *         @OA\Property(property="end_date", type="string", format="date", nullable=true), @OA\Property(property="note", type="string", nullable=true))),
     *
     *     @OA\Response(response=201, description="The repeating cost"), @OA\Response(response=422, description="Invalid")
     * )
     */
    public function store(RecurringExpenseRequest $request): JsonResponse
    {
        $expense = $this->service->create($request->user(), $request->validated());

        // A start date of today (or earlier) is due now: record it straight away.
        $this->service->recordDue($this->today());

        return ApiResponse::success('Repeating cost added', $this->present($expense->refresh()->load(['category:id,name', 'store:id,name']), $this->today()), 201);
    }

    /**
     * @OA\Patch(path="/api/v1/tenant/recurring-expenses/{id}", summary="Change the amount (from the next date) or the last date", operationId="tenantRecurringExpenseUpdate",
     *     tags={"Expenses"}, security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="amount", type="number"), @OA\Property(property="end_date", type="string", format="date", nullable=true), @OA\Property(property="note", type="string", nullable=true))),
     *
     *     @OA\Response(response=200, description="Updated"), @OA\Response(response=404, description="Not a repeating cost")
     * )
     */
    public function update(UpdateRecurringExpenseRequest $request, int $id): JsonResponse
    {
        $expense = $this->find($id);
        $data = $request->validated();

        if (array_key_exists('end_date', $data) && $data['end_date'] !== null && $data['end_date'] < $expense->recurrence_start_date->toDateString()) {
            return ApiResponse::error('The last date cannot be before the first.', ['end_date' => ['The last date cannot be before the first.']], 422);
        }

        $changes = [];
        if (array_key_exists('amount', $data)) {
            $changes['amount'] = $data['amount'];
        }
        if (array_key_exists('note', $data)) {
            $changes['description'] = $data['note'];
        }
        if (array_key_exists('end_date', $data)) {
            $changes['recurrence_end_date'] = $data['end_date'];
            $next = $expense->next_occurrence_date;
            if ($data['end_date'] !== null && $next !== null && $next->toDateString() > $data['end_date']) {
                $changes['next_occurrence_date'] = null;
            }
        }
        $expense->update($changes);

        return ApiResponse::success('Repeating cost updated', $this->present($expense->refresh()->load(['category:id,name', 'store:id,name']), $this->today()));
    }

    /**
     * @OA\Post(path="/api/v1/tenant/recurring-expenses/{id}/stop", summary="Stop a repeating cost after today (past days stay in reports)", operationId="tenantRecurringExpenseStop",
     *     tags={"Expenses"}, security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Stopped"), @OA\Response(response=404, description="Not a repeating cost")
     * )
     */
    public function stop(int $id): JsonResponse
    {
        $expense = $this->service->stop($this->find($id), $this->today());

        return ApiResponse::success('Repeating cost stopped', $this->present($expense->load(['category:id,name', 'store:id,name']), $this->today()));
    }

    private function find(int $id): Expense
    {
        return Expense::query()->where('is_recurring', true)->whereNull('parent_expense_id')->findOrFail($id);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->till->localToday());
    }

    /** @return array<string, mixed> */
    private function present(Expense $e, CarbonImmutable $today): array
    {
        $ended = $e->recurrence_end_date !== null && ($e->recurrence_end_date->lt($today) || $e->next_occurrence_date === null);

        return [
            'id' => $e->id,
            'category' => ['id' => $e->category?->id, 'name' => $e->category?->name],
            'store' => $e->store ? ['id' => $e->store->id, 'name' => $e->store->name] : null,
            'amount' => (float) $e->amount,
            'frequency' => $e->recurrence_frequency?->value ?? (string) $e->recurrence_frequency,
            'note' => $e->description,
            'paid_from' => $e->paid_from?->value ?? $e->paid_from,
            'start_date' => $e->recurrence_start_date?->toDateString(),
            'end_date' => $e->recurrence_end_date?->toDateString(),
            'next_date' => $e->next_occurrence_date?->toDateString(),
            'status' => $ended ? 'ended' : 'active',
            'per_month' => RecurringExpenseService::monthlyEquivalent($e),
        ];
    }
}
