<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Api\Internal\Admin\Concerns\ResolvesShop;
use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\ExtendTrialRequest;
use App\Http\Requests\Internal\Admin\OnboardShopRequest;
use App\Http\Requests\Internal\Admin\ReasonRequest;
use App\Http\Requests\Internal\Admin\VerificationDecisionRequest;
use App\Http\Responses\ApiResponse;
use App\Mail\Central\Business\BusinessVerificationMail;
use App\Mail\Shared\PlainMessageMail;
use App\Models\BillingAccount;
use App\Models\BusinessDetail;
use App\Models\SignupRequest;
use App\Services\Central\Admin\AdminAudit;
use App\Services\Central\Billing\BillingStateService;
use App\Services\Central\Signup\SignupService;
use App\Services\Shared\Messaging\Messenger;
use App\Services\Tenant\TenantAccessService;
use App\Support\ActingAdmin;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Private admin API: shops. Called only by poachy-admin's server (see InternalAdmin middleware).
 */
class ShopController extends Controller
{
    use ResolvesShop;

    public function __construct(private AdminAudit $audit) {}

    /**
     * @OA\Post(
     *     path="/api/internal/admin/shops",
     *     summary="Open a shop for an owner (same provisioning as self sign-up)",
     *     operationId="internalAdminOnboardShop",
     *     tags={"Internal - Admin"},
     *     security={{"internalAdmin": {}}},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"shop_name","owner_name","phone","email","town","workspace"},
     *
     *         @OA\Property(property="shop_name", type="string"), @OA\Property(property="owner_name", type="string"),
     *         @OA\Property(property="phone", type="string", example="0712345678"), @OA\Property(property="email", type="string"),
     *         @OA\Property(property="town", type="string"), @OA\Property(property="workspace", type="string", example="mama-njeri"),
     *         @OA\Property(property="branches", type="integer"), @OA\Property(property="business_category_id", type="integer")
     *     )),
     *
     *     @OA\Response(response=202, description="Provisioning queued; poll GET /shops/onboarding/{id}"),
     *     @OA\Response(response=401, description="Bad service token"), @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Caller not on the allowlist"), @OA\Response(response=422, description="Invalid, or phone/email/workspace taken")
     * )
     */
    public function store(OnboardShopRequest $request, SignupService $signups): JsonResponse
    {
        $admin = ActingAdmin::from($request);
        $signup = $signups->startByAdmin($request->validated(), $admin->id);

        $this->audit->record($request, 'shop.onboard', null, 'signup_request', $signup->id, null,
            ['shop_name' => $signup->shop_name, 'workspace' => $signup->workspace, 'email' => $signup->email]);

        return ApiResponse::success('Shop is being set up', $this->onboardingData($signup), 202);
    }

    /**
     * @OA\Get(path="/api/internal/admin/shops/onboarding/{id}", summary="Status of a shop being opened", operationId="internalAdminOnboardStatus",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\Response(response=200, description="provisioning | ready | failed (+ tenant_id, failure_reason)"),
     *     @OA\Response(response=404, description="Not a staff-opened shop")
     * )
     */
    public function onboarding(string $id): JsonResponse
    {
        $signup = SignupRequest::query()->where('source', SignupRequest::SOURCE_ADMIN)->find($id);

        return $signup === null ? ApiResponse::notFound('Not found.') : ApiResponse::success('Onboarding status', $this->onboardingData($signup));
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/verification", summary="Verification decision (approve → verified badge; reject/revoke need a reason)",
     *     operationId="internalAdminVerification", tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, description="Tenant id or POA account", @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"decision"}, @OA\Property(property="decision", type="string", enum={"approve","reject","revoke"}), @OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=200, description="Decision applied"), @OA\Response(response=404, description="Unknown shop"), @OA\Response(response=422, description="Invalid")
     * )
     */
    public function verification(VerificationDecisionRequest $request, string $shop, Messenger $messenger): JsonResponse
    {
        $tenant = $this->shop($shop);
        $detail = $this->details((string) $tenant->id);
        $before = ['is_verified' => (bool) $detail->is_verified];
        $decision = $request->validated('decision');
        $reason = $request->validated('reason');

        match ($decision) {
            'approve' => $this->setVerified($detail, true, $messenger),
            'revoke' => $this->setVerified($detail, false, $messenger),
            'reject' => $this->reject($detail, (string) $reason, $messenger),
        };

        $detail->refresh();
        $this->audit->record($request, "shop.verification.{$decision}", (string) $tenant->id, 'business_detail', $detail->id, $before, ['is_verified' => (bool) $detail->is_verified], $reason);

        return ApiResponse::success('Verification updated', ['tenant_id' => $tenant->id, 'is_verified' => (bool) $detail->is_verified, 'verified_at' => $detail->verified_at?->toIso8601String()]);
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/suspend", summary="Suspend a shop (app and web access stop)", operationId="internalAdminSuspend",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=200, description="Suspended"), @OA\Response(response=404, description="Unknown shop")
     * )
     */
    public function suspend(ReasonRequest $request, string $shop): JsonResponse
    {
        return $this->setStatus($request, $shop, 'suspended', 'shop.suspend');
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/restore", summary="Restore a suspended shop", operationId="internalAdminRestore",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"reason"}, @OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=200, description="Active again"), @OA\Response(response=404, description="Unknown shop")
     * )
     */
    public function restore(ReasonRequest $request, string $shop): JsonResponse
    {
        return $this->setStatus($request, $shop, 'active', 'shop.restore');
    }

    /**
     * @OA\Post(path="/api/internal/admin/shops/{shop}/trial", summary="Extend (or reopen) the free trial by N days", operationId="internalAdminExtendTrial",
     *     tags={"Internal - Admin"}, security={{"internalAdmin": {}}},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"days","reason"}, @OA\Property(property="days", type="integer", minimum=1, maximum=90), @OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=200, description="New trial end"), @OA\Response(response=404, description="Unknown shop or no billing account")
     * )
     */
    public function extendTrial(ExtendTrialRequest $request, string $shop, BillingStateService $states): JsonResponse
    {
        $tenant = $this->shop($shop);
        $account = BillingAccount::query()->where('tenant_id', $tenant->id)->first();
        if ($account === null) {
            return ApiResponse::notFound('This shop is not on trading-day billing.');
        }

        $before = ['status' => $account->status->value, 'trial_ends_at' => $account->trial_ends_at?->toIso8601String()];
        $days = (int) $request->validated('days');

        DB::connection('central')->transaction(function () use ($account, $days) {
            $locked = BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            // A running trial is lengthened; an ended one (or none) starts again from now.
            $from = $locked->trial_ends_at !== null && $locked->trial_ends_at->isFuture() ? CarbonImmutable::parse($locked->trial_ends_at) : CarbonImmutable::now();
            $locked->forceFill(['trial_ends_at' => $from->addDays($days)->utc()])->save();
        });

        $account = $states->evaluate($account->fresh());
        $after = ['status' => $account->status->value, 'trial_ends_at' => $account->trial_ends_at?->toIso8601String()];
        $this->audit->record($request, 'shop.trial.extend', (string) $tenant->id, 'billing_account', $account->id, $before, $after + ['days' => $days], $request->validated('reason'));

        return ApiResponse::success('Trial extended', ['tenant_id' => $tenant->id] + $after);
    }

    private function setStatus(ReasonRequest $request, string $shop, string $status, string $action): JsonResponse
    {
        $tenant = $this->shop($shop);
        $detail = $this->details((string) $tenant->id);
        $before = ['status' => $detail->status];

        if ($action === 'shop.restore' && $detail->status !== 'suspended') {
            return ApiResponse::error('Only a suspended shop can be restored.', ['status' => [$detail->status]], 422);
        }

        $detail->forceFill(['status' => $status])->save();
        app(TenantAccessService::class)->clearTenantAccessCache((string) $tenant->id);
        $this->audit->record($request, $action, (string) $tenant->id, 'business_detail', $detail->id, $before, ['status' => $status], $request->validated('reason'));

        return ApiResponse::success($status === 'suspended' ? 'Shop suspended' : 'Shop restored', ['tenant_id' => $tenant->id, 'status' => $status]);
    }

    private function setVerified(BusinessDetail $detail, bool $verified, Messenger $messenger): void
    {
        $verified ? $detail->verify() : $detail->unverify();
        app(TenantAccessService::class)->clearTenantAccessCache($detail->tenant_id);

        if ($detail->business_email) {
            $messenger->email($detail->business_email, new BusinessVerificationMail(
                ownerName: (string) ($detail->contact_person ?: $detail->business_name),
                businessName: $detail->business_name,
                isVerified: $verified,
            ), $detail->tenant_id, $verified ? 'verification_approved' : 'verification_revoked');
        }
    }

    private function reject(BusinessDetail $detail, string $reason, Messenger $messenger): void
    {
        if ($detail->is_verified) {
            $detail->unverify();
        }
        if ($detail->business_email) {
            $messenger->email($detail->business_email, new PlainMessageMail(
                'We could not verify your shop yet',
                ["We reviewed {$detail->business_name} but could not verify it yet:", $reason, 'You can keep selling as normal. Send us what is missing and we will look again.'],
            ), $detail->tenant_id, 'verification_rejected');
        }
    }

    private function details(string $tenantId): BusinessDetail
    {
        $detail = BusinessDetail::query()->where('tenant_id', $tenantId)->first();
        abort_if($detail === null, 404, 'Shop has no business details.');

        return $detail;
    }

    /** @return array<string, mixed> */
    private function onboardingData(SignupRequest $signup): array
    {
        return [
            'id' => $signup->id,
            'status' => $signup->status,
            'workspace' => $signup->workspace,
            'tenant_id' => $signup->tenant_id,
            'failure_reason' => $signup->status === SignupRequest::FAILED ? $signup->failure_reason : null,
        ];
    }
}
