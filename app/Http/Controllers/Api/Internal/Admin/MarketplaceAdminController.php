<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\DecideReturnRequest;
use App\Http\Requests\Internal\Admin\MarkPayoutPaidRequest;
use App\Http\Requests\Internal\Admin\ModerateReviewAdminRequest;
use App\Http\Requests\Internal\Admin\ReasonRequest;
use App\Http\Requests\Internal\Admin\UpdateAdvertTypeRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Advert;
use App\Models\AdvertType;
use App\Models\BusinessDetail;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceRefund;
use App\Models\MarketplaceReturnCase;
use App\Models\MerchantReview;
use App\Models\ProductReview;
use App\Services\Central\Admin\AdminAudit;
use App\Services\Central\Marketplace\AdvertService;
use App\Services\Central\Marketplace\ReviewModerationService;
use App\Services\Central\Payouts\PayoutService;
use App\Services\Central\Refunds\RefundService;
use App\Services\Central\Refunds\ReturnCaseService;
use App\Support\ActingAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marketplace actions for Poachy staff (poachy-admin): dispute and escalation decisions,
 * refund re-sends, payout re-sends / manual confirmation / holds, and review moderation.
 * Every change is audited with the acting admin.
 */
class MarketplaceAdminController extends Controller
{
    public function __construct(private readonly AdminAudit $audit) {}

    /**
     * Poachy decides a disputed or escalated return.
     *
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/returns/{reference}/decide",
     *     summary="Poachy decides a disputed or escalated return; a refund goes out at once (super, operations)",
     *     operationId="internalAdminReturnDecide",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="decision", type="string", enum={"refund_full","refund_partial","no_refund"}),
     *         @OA\Property(property="refund_amount", type="number", nullable=true),
     *         @OA\Property(property="note", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function decideReturn(DecideReturnRequest $request, string $reference, ReturnCaseService $returns): JsonResponse
    {
        $case = MarketplaceReturnCase::query()->where('reference', $reference)->first();
        if ($case === null) {
            return ApiResponse::notFound('Return not found.');
        }
        $before = ['status' => $case->status, 'refund_amount' => $case->refund_amount];
        $amount = $request->validated('refund_amount');
        $case = $returns->decide($case, $request->validated('decision'), $amount === null ? null : (float) $amount, $request->validated('note'), ActingAdmin::from($request)->tag());
        $this->audit->record($request, 'marketplace.return.decide', $case->tenant_id, 'marketplace_return_case', $case->id, $before,
            ['status' => $case->status, 'decision' => $case->admin_decision, 'refund_amount' => $case->refund_amount], $request->validated('note'));

        return ApiResponse::success('Decision saved', ['status' => $case->status, 'refund_amount' => (float) $case->refund_amount]);
    }

    /**
     * Evidence photos of a return: buyer, shop or dispute (0-based).
     *
     * @OA\Get(
     *     path="/api/internal/admin/marketplace/returns/{reference}/photos/{who}/{index}",
     *     summary="A return evidence photo (buyer, shop or dispute) (super, operations, support)",
     *     operationId="internalAdminReturnPhoto",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="reference", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="who", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="index", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function returnPhoto(string $reference, string $who, int $index): StreamedResponse|JsonResponse
    {
        $case = MarketplaceReturnCase::query()->where('reference', $reference)->first();
        $paths = match ($who) {
            'buyer' => $case?->buyer_evidence,
            'shop' => $case?->shop_evidence,
            default => $case?->dispute_evidence,
        };
        $path = ($paths ?? [])[$index] ?? null;
        if ($path === null || ! Storage::disk('returns')->exists($path)) {
            return ApiResponse::notFound('Photo not found.');
        }

        return Storage::disk('returns')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/refunds/{id}/retry",
     *     summary="Send a failed buyer refund again (super, finance)",
     *     operationId="internalAdminRefundRetry",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function retryRefund(ReasonRequest $request, int $id, RefundService $refunds): JsonResponse
    {
        $failed = MarketplaceRefund::query()->find($id);
        if ($failed === null) {
            return ApiResponse::notFound('Refund not found.');
        }
        try {
            $refund = $refunds->retry($failed);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
        $this->audit->record($request, 'marketplace.refund.retry', $failed->tenant_id, 'marketplace_refund', $failed->id,
            ['status' => $failed->status], ['new_refund_id' => $refund->id, 'status' => $refund->status, 'amount' => $refund->amount], $request->validated('reason'));

        return ApiResponse::success('Refund sent again', ['id' => $refund->id, 'status' => $refund->status, 'method' => $refund->method]);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/payouts/{id}/retry",
     *     summary="Send a failed / checked payout again (super, finance)",
     *     operationId="internalAdminPayoutRetry",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function retryPayout(ReasonRequest $request, int $id, PayoutService $payouts): JsonResponse
    {
        $payout = MarketplacePayout::query()->find($id);
        if ($payout === null) {
            return ApiResponse::notFound('Payout not found.');
        }
        $before = ['status' => $payout->status, 'attempts' => $payout->attempts];
        try {
            $payout = $payouts->retryByAdmin($payout);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
        $this->audit->record($request, 'marketplace.payout.retry', $payout->tenant_id, 'marketplace_payout', $payout->id, $before, ['status' => $payout->status], $request->validated('reason'));

        return ApiResponse::success('Payout sent again', ['status' => $payout->status]);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/payouts/{id}/mark-paid",
     *     summary="Confirm from the M-Pesa statement that a payout went out (super, finance)",
     *     operationId="internalAdminPayoutMarkPaid",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="mpesa_receipt", type="string"),
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function markPayoutPaid(MarkPayoutPaidRequest $request, int $id, PayoutService $payouts): JsonResponse
    {
        $payout = MarketplacePayout::query()->find($id);
        if ($payout === null) {
            return ApiResponse::notFound('Payout not found.');
        }
        $before = ['status' => $payout->status, 'mpesa_receipt' => $payout->mpesa_receipt];
        try {
            $payout = $payouts->markPaidByAdmin($payout, $request->validated('mpesa_receipt'));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
        $this->audit->record($request, 'marketplace.payout.mark_paid', $payout->tenant_id, 'marketplace_payout', $payout->id, $before,
            ['status' => $payout->status, 'mpesa_receipt' => $payout->mpesa_receipt], $request->validated('reason'));

        return ApiResponse::success('Payout marked paid', ['status' => $payout->status]);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/shops/{shop}/payouts/hold",
     *     summary="Hold a shop's marketplace payouts (super, finance, operations)",
     *     operationId="internalAdminPayoutsHold",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function holdPayouts(ReasonRequest $request, string $shop): JsonResponse
    {
        return $this->setHold($request, $shop, true);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/shops/{shop}/payouts/release",
     *     summary="Release a shop's held payouts (super, finance)",
     *     operationId="internalAdminPayoutsRelease",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="shop", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function releasePayouts(ReasonRequest $request, string $shop): JsonResponse
    {
        return $this->setHold($request, $shop, false);
    }

    /**
     * approve | reject | dismiss_flags on a product or shop review.
     *
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/reviews/{type}/{id}/moderate",
     *     summary="Approve, reject or clear flags on a product or shop review (super, operations, support)",
     *     operationId="internalAdminReviewModerate",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="type", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="action", type="string", enum={"approve","reject","dismiss_flags"}),
     *         @OA\Property(property="rejection_reason", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="Done (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Invalid or not possible in this state")
     * )
     */
    public function moderateReview(ModerateReviewAdminRequest $request, string $type, int $id, ReviewModerationService $moderation): JsonResponse
    {
        $review = $type === 'product' ? ProductReview::query()->find($id) : MerchantReview::query()->find($id);
        if ($review === null) {
            return ApiResponse::notFound('Review not found.');
        }
        $before = ['status' => $review->status?->value ?? $review->status];
        $adminId = (int) ActingAdmin::from($request)->id;
        try {
            $review = $type === 'product'
                ? $moderation->moderateProductReview($review, $request->validated('action'), $adminId, $request->validated('rejection_reason'))
                : $moderation->moderateMerchantReview($review, $request->validated('action'), $adminId, $request->validated('rejection_reason'));
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), null, 422);
        }
        $after = ['status' => $review->status?->value ?? $review->status];
        $this->audit->record($request, "marketplace.review.{$request->validated('action')}", $review->tenant_id ?? null, "{$type}_review", $review->id, $before, $after, $request->validated('rejection_reason'));

        return ApiResponse::success('Review moderated', $after);
    }

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/adverts/{id}/stop",
     *     summary="Stop an advert (moderation); unused days go back to the shop's balance (super, operations)",
     *     operationId="internalAdminAdvertStop",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="reason", type="string"))),
     *
     *     @OA\Response(response=200, description="Stopped (audited)"),
     *     @OA\Response(response=403, description="Role not allowed"),
     *     @OA\Response(response=422, description="Not running")
     * )
     */
    public function stopAdvert(ReasonRequest $request, int $id, AdvertService $adverts): JsonResponse
    {
        $advert = Advert::query()->find($id);
        if ($advert === null) {
            return ApiResponse::notFound('Advert not found.');
        }
        $before = ['status' => $advert->status, 'ends_at' => $advert->ends_at];
        $advert = $adverts->stop($advert, $request->validated('reason'), ActingAdmin::from($request)->tag());
        $this->audit->record($request, 'marketplace.advert.stop', $advert->tenant_id, 'advert', $advert->id, $before, ['status' => $advert->status], $request->validated('reason'));

        return ApiResponse::success('Advert stopped', ['status' => $advert->status]);
    }

    /**
     * @OA\Patch(
     *     path="/api/internal/admin/marketplace/advert-types/{key}",
     *     summary="Change an advert type's price, length or availability; running adverts keep what they paid (super, finance)",
     *     operationId="internalAdminAdvertTypeUpdate",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="key", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="price", type="number"), @OA\Property(property="days", type="integer"),
     *         @OA\Property(property="is_active", type="boolean"), @OA\Property(property="reason", type="string")
     *     )),
     *
     *     @OA\Response(response=200, description="Updated (audited)"),
     *     @OA\Response(response=403, description="Role not allowed")
     * )
     */
    public function updateAdvertType(UpdateAdvertTypeRequest $request, string $key): JsonResponse
    {
        $type = AdvertType::query()->where('key', $key)->first();
        if ($type === null) {
            return ApiResponse::notFound('Advert type not found.');
        }
        $before = $type->only(['price', 'days', 'is_active']);
        $type->update($request->safe()->only(['price', 'days', 'is_active']));
        $this->audit->record($request, 'marketplace.advert_type.update', null, 'advert_type', $type->id, $before, $type->fresh()->only(['price', 'days', 'is_active']), $request->validated('reason'));

        return ApiResponse::success('Advert price saved', $type->fresh()->only(['key', 'price', 'days', 'is_active']));
    }

    private function setHold(ReasonRequest $request, string $shop, bool $hold): JsonResponse
    {
        $details = BusinessDetail::query()->where('tenant_id', $shop)->first();
        if ($details === null) {
            return ApiResponse::notFound('Shop not found.');
        }
        $before = ['payouts_held_at' => $details->payouts_held_at, 'payouts_hold_reason' => $details->payouts_hold_reason];
        $details->update($hold
            ? ['payouts_held_at' => now(), 'payouts_hold_reason' => $request->validated('reason')]
            : ['payouts_held_at' => null, 'payouts_hold_reason' => null]);
        $this->audit->record($request, $hold ? 'marketplace.payouts.hold' : 'marketplace.payouts.release', $shop, 'business_detail', $details->id, $before,
            ['payouts_held_at' => $details->payouts_held_at], $request->validated('reason'));

        return ApiResponse::success($hold ? 'Payouts on hold' : 'Payouts released', ['held' => $hold]);
    }
}
