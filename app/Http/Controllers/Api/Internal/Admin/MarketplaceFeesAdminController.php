<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\ScheduleCommissionRateRequest;
use App\Http\Requests\Internal\Admin\ScheduleMarketplaceFeesRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceCommissionRate;
use App\Models\MarketplaceFeeSetting;
use App\Models\MarketplacePayoutLine;
use App\Services\Central\Admin\AdminAudit;
use App\Services\Central\Payouts\CommissionService;
use App\Services\Central\Payouts\MarketplaceFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Item 49: Poachy finance sets the payout processing fees and the marketplace commission in the
 * admin, from a date on (never back-dated: lines already recorded keep what they were charged),
 * and sees what was actually charged.
 */
class MarketplaceFeesAdminController extends Controller
{
    public function __construct(
        private readonly AdminAudit $audit,
        private readonly MarketplaceFeeService $fees,
        private readonly CommissionService $commission,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/internal/admin/marketplace/fees",
     *     summary="Payout fees and commission: in force today, scheduled, history, and the last 30 days' totals",
     *     operationId="internalAdminMarketplaceFees",
     *     tags={"Internal - Admin"},
     *     security={{"internalAdmin": {}}},
     *
     *     @OA\Response(response=200, description="current{fees, commission_percent}, fee_history[], commission_history[], last_30_days{orders, gross, processing_fees, commission, net}"),
     *     @OA\Response(response=403, description="Role not allowed")
     * )
     */
    public function index(): JsonResponse
    {
        $since = now()->subDays(30);
        $totals = MarketplacePayoutLine::query()->where('type', MarketplacePayoutLine::SALE)->where('handed_over_at', '>=', $since)
            ->select(DB::raw('COUNT(*) n, COALESCE(SUM(gross),0) g, COALESCE(SUM(processing_fee),0) f, COALESCE(SUM(commission_amount),0) c, COALESCE(SUM(net),0) t'))->first();

        return ApiResponse::success('Marketplace fees', [
            'current' => ['fees' => $this->fees->on(), 'commission_percent' => $this->commission->rateOn(now())],
            'fee_history' => MarketplaceFeeSetting::query()->orderByDesc('effective_from')->orderByDesc('id')->limit(50)->get(),
            'commission_history' => MarketplaceCommissionRate::query()->orderByDesc('effective_from')->orderByDesc('id')->limit(50)->get(),
            'last_30_days' => [
                'orders' => (int) $totals->n,
                'gross' => round((float) $totals->g, 2),
                'processing_fees' => round((float) $totals->f, 2),
                'commission' => round((float) $totals->c, 2),
                'net' => round((float) $totals->t, 2),
            ],
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/fees",
     *     summary="Schedule payout fees, hold and minimum from a date (super, finance)",
     *     operationId="internalAdminScheduleMarketplaceFees",
     *     tags={"Internal - Admin"},
     *     security={{"internalAdmin": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"mpesa_fee_percent","card_fee_percent","transfer_fee","hold_hours","min_payout","effective_from","reason"},
     *
     *         @OA\Property(property="mpesa_fee_percent", type="number", example=0.55),
     *         @OA\Property(property="card_fee_percent", type="number", example=2.9),
     *         @OA\Property(property="transfer_fee", type="number", example=0),
     *         @OA\Property(property="hold_hours", type="integer", example=24),
     *         @OA\Property(property="min_payout", type="integer", example=10),
     *         @OA\Property(property="effective_from", type="string", format="date"),
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=201, description="Scheduled (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=422, description="Out of range or back-dated")
     * )
     */
    public function scheduleFees(ScheduleMarketplaceFeesRequest $request): JsonResponse
    {
        $data = $request->safe()->except('reason');
        $before = $this->fees->on();
        $row = MarketplaceFeeSetting::query()->create($data + ['notes' => $request->validated('reason')]);
        $this->audit->record($request, 'marketplace.fees.schedule', null, 'marketplace_fee_setting', $row->id, $before, $data, $request->validated('reason'));

        return ApiResponse::created('Fees scheduled from '.$row->effective_from->format('j M Y'), $row);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/commission-rates",
     *     summary="Schedule the marketplace commission rate from a date (super, finance); own-link orders stay at 0%",
     *     operationId="internalAdminScheduleCommissionRate",
     *     tags={"Internal - Admin"},
     *     security={{"internalAdmin": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"rate_percent","effective_from","reason"},
     *
     *         @OA\Property(property="rate_percent", type="number", example=5),
     *         @OA\Property(property="effective_from", type="string", format="date"),
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=201, description="Scheduled (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=422, description="Out of range or back-dated")
     * )
     */
    public function scheduleCommission(ScheduleCommissionRateRequest $request): JsonResponse
    {
        $before = ['rate_percent' => $this->commission->rateOn(now())];
        $row = MarketplaceCommissionRate::query()->create([
            'rate_percent' => $request->validated('rate_percent'),
            'effective_from' => $request->validated('effective_from'),
            'notes' => $request->validated('reason'),
        ]);
        $this->audit->record($request, 'marketplace.commission.schedule', null, 'marketplace_commission_rate', $row->id, $before,
            ['rate_percent' => $row->rate_percent, 'effective_from' => $row->effective_from->toDateString()], $request->validated('reason'));

        return ApiResponse::created('Commission scheduled from '.$row->effective_from->format('j M Y'), $row);
    }
}
