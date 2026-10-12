<?php

namespace App\Http\Controllers\Api\Tenant\Billing;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Billing\BillingLedgerEntryResource;
use App\Http\Responses\ApiResponse;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The shop's Poachy billing status and statement (app banner, More → Billing, web /business).
 * Outside tenant.access/billing.limits: a limited shop must always see how to pay.
 */
class BillingController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tenant/billing",
     *     summary="Billing status: balance, state, this month, Paybill details",
     *     description="Any signed-in user gets the state and how to pay; owners and managers also get the money figures.",
     *     operationId="tenantBillingShow",
     *     tags={"Tenant - Billing"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Response(response=200, description="status (trial|active|grace|limited), balance, days_covered, is_low, month{days_billed,amount}, paybill_number, account_number, prices"),
     *     @OA\Response(response=401, description="Signed out"),
     *     @OA\Response(response=404, description="The shop has no billing account")
     * )
     */
    public function show(Request $request, BillingStateService $states): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $account = BillingAccount::query()->where('tenant_id', $tenantId)->first();

        if ($account === null) {
            return ApiResponse::notFound('This shop is not on trading-day billing.');
        }

        $price = BillingPrice::forDate(now('Africa/Nairobi'));
        $data = [
            'status' => $account->status->value,
            'status_label' => $account->status->label(),
            'trial_ends_at' => $account->trial_ends_at?->toIso8601String(),
            'grace_until' => $account->grace_until?->toIso8601String(),
            'limited_since' => $account->limited_since?->toIso8601String(),
            'paybill_number' => (string) config('messaging.paybill_number'),
            'account_number' => (string) Tenant::query()->whereKey($tenantId)->value('mpesa_paybill_account'),
            'min_topup' => (float) ($price?->min_topup ?? 10),
        ];

        if ($this->seesMoney($request)) {
            $month = $states->monthToDate($tenantId);
            $data += [
                'balance' => (float) $account->balance,
                'is_low' => $states->isLow($account, $price),
                'days_covered' => $states->daysCovered($account, $price),
                'daily_cost' => $states->dailyCost($tenantId, $price),
                'month' => ['days_billed' => $month['days'], 'amount' => $month['amount']],
                'prices' => $price === null ? null : [
                    'first_branch_day' => (float) $price->first_branch_day,
                    'extra_branch_day' => (float) $price->extra_branch_day,
                    'first_branch_cap' => (float) $price->first_branch_cap,
                    'extra_branch_cap' => (float) $price->extra_branch_cap,
                ],
            ];
        }

        return ApiResponse::success('Billing status', $data);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/billing/ledger",
     *     summary="Billing statement, newest first (owner, manager)",
     *     operationId="tenantBillingLedger",
     *     tags={"Tenant - Billing"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1)),
     *
     *     @OA\Response(response=200, description="data.data[] (type, amount, balance_after, description, reference, created_at) + data.pagination"),
     *     @OA\Response(response=403, description="Cashiers cannot see the statement")
     * )
     */
    public function ledger(Request $request): JsonResponse
    {
        if (! $this->seesMoney($request)) {
            return ApiResponse::forbidden('Only owners and managers can see the billing statement.');
        }

        $perPage = max(1, min(100, $request->integer('per_page', 30)));
        $entries = BillingLedgerEntry::query()
            ->where('tenant_id', (string) tenant('id'))
            ->orderByDesc('id')
            ->paginate($perPage);

        return ApiResponse::paginated(BillingLedgerEntryResource::collection($entries), 'Billing statement');
    }

    private function seesMoney(Request $request): bool
    {
        return $request->user()?->hasAnyRole(['owner', 'manager', 'admin']) ?? false;
    }
}
