<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Enums\Central\BillingLedgerType;
use App\Events\Central\Billing\BillingBalanceCredited;
use App\Exceptions\MpesaException;
use App\Http\Controllers\Api\Internal\Admin\Concerns\ResolvesShop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\BillingCreditRequest;
use App\Http\Requests\Internal\Admin\BillingLinkRequest;
use App\Http\Requests\Internal\Admin\BillingPriceRequest;
use App\Http\Requests\Internal\Admin\BillingStkRequest;
use App\Http\Resources\Tenant\Billing\BillingLedgerEntryResource;
use App\Http\Resources\Tenant\Billing\BillingTopupResource;
use App\Http\Responses\ApiResponse;
use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use App\Models\BillingPrice;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Services\Central\Admin\AdminAudit;
use App\Services\Central\Billing\BillingLedgerService;
use App\Services\Central\Billing\BillingNotices;
use App\Services\Central\Billing\BillingTopupService;
use App\Services\Central\Billing\PaymentLinkService;
use App\Support\ActingAdmin;
use Illuminate\Http\JsonResponse;

/**
 * Private admin API: shop billing and prices. Every action is in the admin audit log; money
 * actions also need an Idempotency-Key.
 */
class BillingController extends Controller
{
    use ResolvesShop;

    public function __construct(private AdminAudit $audit) {}

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/billing/credit", summary="Manual credit to a shop's Poachy balance", operationId="internalAdminBillingCredit",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string", maxLength=100)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"amount","reason"}, @OA\Property(property="amount", type="number", minimum=1), @OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=201, description="Ledger entry (same key → the same entry, 200)"), @OA\Response(response=404, description="Unknown shop"), @OA\Response(response=422, description="Invalid")
     * )
     */
    public function credit(BillingCreditRequest $request, string $shop, BillingLedgerService $ledger): JsonResponse
    {
        $tenant = $this->shop($shop);
        $account = $this->account((string) $tenant->id);
        $admin = ActingAdmin::from($request);
        $key = 'admin-credit:'.$tenant->id.':'.$request->idempotencyKey();

        if ($existing = BillingLedgerEntry::query()->where('idempotency_key', $key)->first()) {
            return ApiResponse::success('Already credited', new BillingLedgerEntryResource($existing));
        }

        $before = ['balance' => (float) $account->balance];
        $entry = $ledger->post($account, BillingLedgerType::Adjustment, (float) $request->validated('amount'), 'Credit from Poachy', 'admin',
            reference: null, idempotencyKey: $key, createdBy: $admin->tag(), meta: ['reason' => $request->validated('reason')]);
        BillingBalanceCredited::dispatch($account->fresh(), $entry);

        $this->audit->record($request, 'billing.credit', (string) $tenant->id, 'billing_ledger_entry', $entry->id, $before,
            ['balance' => (float) $entry->balance_after, 'amount' => (float) $entry->amount], $request->validated('reason'));

        return ApiResponse::success('Credited', new BillingLedgerEntryResource($entry), 201);
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/billing/stk", summary="Send an M-Pesa top-up prompt to the shop", operationId="internalAdminBillingStk",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string", maxLength=100)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"amount"}, @OA\Property(property="amount", type="integer"), @OA\Property(property="phone", type="string", description="Defaults to the business phone"))),
     *
     *     @OA\Response(response=201, description="Prompt sent; poll GET .../billing/topups/{id}"), @OA\Response(response=422, description="Invalid or M-Pesa refused")
     * )
     */
    public function stk(BillingStkRequest $request, string $shop, BillingTopupService $topups): JsonResponse
    {
        $tenant = $this->shop($shop);
        $this->account((string) $tenant->id);
        $admin = ActingAdmin::from($request);
        $phone = $request->validated('phone') ?? BusinessDetail::query()->where('tenant_id', $tenant->id)->value('business_phone');

        if (! $phone) {
            return ApiResponse::error('The shop has no business phone; give one.', ['phone' => ['Required for this shop.']], 422);
        }

        $key = 'admin:'.$request->idempotencyKey();
        if ($existing = BillingTopup::query()->where('idempotency_key', "{$tenant->id}:{$key}")->first()) {
            return ApiResponse::success('Prompt already sent', new BillingTopupResource($existing));
        }

        try {
            $topup = $topups->startStk((string) $tenant->id, (float) $request->validated('amount'), (string) $phone, $admin->tag(), $key);
        } catch (MpesaException $e) {
            return ApiResponse::error('M-Pesa could not send the prompt: '.$e->getMessage(), ['error_code' => $e->darajaErrorCode], 422);
        }

        $this->audit->record($request, 'billing.stk', (string) $tenant->id, 'billing_topup', $topup->id, null,
            ['amount' => (float) $topup->amount, 'status' => $topup->status]);

        return ApiResponse::success('Prompt sent', new BillingTopupResource($topup), 201);
    }

    /**
     * @OA\Get(path="/api/internal/admin/shops/{shop}/billing/topups/{id}", summary="Status of a top-up prompt", operationId="internalAdminBillingTopup",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")), @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="pending | processing | completed | failed"), @OA\Response(response=404, description="Not this shop's")
     * )
     */
    public function topup(string $shop, int $id): JsonResponse
    {
        $tenant = $this->shop($shop);
        $topup = BillingTopup::query()->where('tenant_id', $tenant->id)->find($id);

        return $topup === null ? ApiResponse::notFound('Top-up not found.') : ApiResponse::success('Top-up', new BillingTopupResource($topup));
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/billing/link", summary="Make a 7-day pay link (and optionally email it to the shop)", operationId="internalAdminBillingLink",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(@OA\JsonContent(@OA\Property(property="amount", type="integer"), @OA\Property(property="email", type="boolean"))),
     *
     *     @OA\Response(response=201, description="url, expires_at, emailed_to")
     * )
     */
    public function link(BillingLinkRequest $request, string $shop, PaymentLinkService $links, BillingNotices $notices): JsonResponse
    {
        $tenant = $this->shop($shop);
        $this->account((string) $tenant->id);
        $admin = ActingAdmin::from($request);
        $amount = $request->validated('amount') !== null ? (float) $request->validated('amount') : null;

        $made = $links->create((string) $tenant->id, $amount, $admin->tag());
        $emailedTo = $request->boolean('email') ? $notices->paymentLink((string) $tenant->id, $made['url'], $amount) : null;

        $this->audit->record($request, 'billing.link', (string) $tenant->id, 'payment_link', $made['link']->id, null,
            ['amount' => $amount, 'emailed' => $emailedTo !== null]);

        return ApiResponse::success('Payment link created', [
            'url' => $made['url'],
            'expires_at' => $made['link']->expires_at->toIso8601String(),
            'emailed_to' => $emailedTo,
        ], 201);
    }

    /**
     * @OA\Get(path="/api/internal/admin/billing/prices", summary="Price history (newest first)", operationId="internalAdminPrices",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}}, @OA\Response(response=200, description="Price rows")
     * )
     */
    public function prices(): JsonResponse
    {
        return ApiResponse::success('Prices', BillingPrice::query()->orderByDesc('effective_from')->get()->map(fn (BillingPrice $p) => $this->priceData($p)));
    }

    /**
     * @OA\Post(path="/api/internal/admin/billing/prices", summary="Schedule new prices from a future date", operationId="internalAdminPriceStore",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"effective_from","first_branch_day","extra_branch_day","first_branch_cap","extra_branch_cap","trial_days","min_topup","low_balance_days","grace_days"},
     *
     *         @OA\Property(property="effective_from", type="string", format="date"), @OA\Property(property="first_branch_day", type="number"), @OA\Property(property="extra_branch_day", type="number"),
     *         @OA\Property(property="first_branch_cap", type="number"), @OA\Property(property="extra_branch_cap", type="number"), @OA\Property(property="trial_days", type="integer"),
     *         @OA\Property(property="min_topup", type="number"), @OA\Property(property="low_balance_days", type="integer"), @OA\Property(property="grace_days", type="integer"), @OA\Property(property="notes", type="string"))),
     *
     *     @OA\Response(response=201, description="Created"), @OA\Response(response=409, description="A price already starts that day"), @OA\Response(response=422, description="Past date or invalid")
     * )
     */
    public function storePrice(BillingPriceRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (BillingPrice::query()->whereDate('effective_from', $data['effective_from'])->exists()) {
            return ApiResponse::conflict('Prices already change on that day.');
        }

        $price = BillingPrice::query()->create($data + ['created_by' => ActingAdmin::from($request)->tag()]);
        $this->audit->record($request, 'billing.price.create', null, 'billing_price', $price->id, null, $this->priceData($price), $data['notes'] ?? null);

        return ApiResponse::success('Prices scheduled', $this->priceData($price), 201);
    }

    /** @return array<string, mixed> */
    private function priceData(BillingPrice $p): array
    {
        return [
            'id' => $p->id,
            'effective_from' => $p->effective_from->toDateString(),
            'first_branch_day' => (float) $p->first_branch_day,
            'extra_branch_day' => (float) $p->extra_branch_day,
            'first_branch_cap' => (float) $p->first_branch_cap,
            'extra_branch_cap' => (float) $p->extra_branch_cap,
            'trial_days' => (int) $p->trial_days,
            'min_topup' => (float) $p->min_topup,
            'low_balance_days' => (int) $p->low_balance_days,
            'grace_days' => (int) $p->grace_days,
            'notes' => $p->notes,
        ];
    }

    private function account(string $tenantId): BillingAccount
    {
        $account = BillingAccount::query()->where('tenant_id', $tenantId)->first();
        abort_if($account === null, 404, 'This shop is not on trading-day billing.');

        return $account;
    }
}
