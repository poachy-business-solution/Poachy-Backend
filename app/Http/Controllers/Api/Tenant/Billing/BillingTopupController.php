<?php

namespace App\Http\Controllers\Api\Tenant\Billing;

use App\Exceptions\MpesaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Billing\StartBillingTopupRequest;
use App\Http\Resources\Tenant\Billing\BillingTopupResource;
use App\Http\Responses\ApiResponse;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Services\Central\Billing\BillingTopupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Top up the shop's Poachy balance by M-Pesa prompt. Used by the web business account
 * (/business/billing) — never by the POS app, which shows Paybill details only.
 */
class BillingTopupController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/v1/tenant/billing/topups/stk",
     *     summary="Top up the Poachy balance by M-Pesa prompt (owner)",
     *     operationId="tenantBillingTopupStk",
     *     tags={"Tenant - Billing"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string", maxLength=100)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"amount"},
     *
     *         @OA\Property(property="amount", type="integer", minimum=10, maximum=150000, example=700),
     *         @OA\Property(property="phone", type="string", nullable=true, example="0712345678", description="Defaults to the shop's business phone")
     *     )),
     *
     *     @OA\Response(response=201, description="Prompt sent; poll GET /billing/topups/{id}"),
     *     @OA\Response(response=200, description="Same Idempotency-Key: the earlier top-up"),
     *     @OA\Response(response=403, description="Not the owner"),
     *     @OA\Response(response=422, description="Invalid amount/phone, missing key, or M-Pesa refused the prompt"),
     *     @OA\Response(response=429, description="Too many prompts")
     * )
     */
    public function stk(StartBillingTopupRequest $request, BillingTopupService $topups): JsonResponse
    {
        $tenantId = (string) tenant('id');
        $phone = $request->input('phone') ?: BusinessDetail::query()->where('tenant_id', $tenantId)->value('business_phone');

        if (! $phone) {
            return ApiResponse::error('Add a phone number to send the M-Pesa prompt to.', ['phone' => ['required']], 422);
        }

        $key = (string) $request->header('Idempotency-Key');
        $replay = BillingTopup::query()->where('idempotency_key', "{$tenantId}:{$key}")->exists();

        try {
            $topup = $topups->startStk($tenantId, (float) $request->integer('amount'), (string) $phone, 'tenant_user:'.$request->user()->id, $key);
        } catch (MpesaException $e) {
            return ApiResponse::error('M-Pesa could not send the prompt: '.$e->getMessage(), ['error_code' => $e->darajaErrorCode], 422);
        }

        return ApiResponse::success(
            $replay ? 'Top-up already started' : 'Check your phone and enter your M-Pesa PIN',
            new BillingTopupResource($topup),
            $replay ? 200 : 201,
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/billing/topups/{id}",
     *     summary="Status of a top-up prompt (owner)",
     *     operationId="tenantBillingTopupShow",
     *     tags={"Tenant - Billing"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="pending | processing | completed | failed"),
     *     @OA\Response(response=403, description="Not the owner"),
     *     @OA\Response(response=404, description="Not this shop's top-up")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        if (! $request->user()?->hasRole('owner')) {
            return ApiResponse::forbidden('Only the owner can see top-ups.');
        }

        $topup = BillingTopup::query()->where('tenant_id', (string) tenant('id'))->find($id);

        return $topup === null
            ? ApiResponse::notFound('Top-up not found.')
            : ApiResponse::success('Top-up', new BillingTopupResource($topup));
    }
}
