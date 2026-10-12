<?php

namespace App\Http\Controllers\Api\Public;

use App\Exceptions\MpesaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PayLinkStkRequest;
use App\Http\Resources\Tenant\Billing\BillingTopupResource;
use App\Http\Responses\ApiResponse;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\PaymentLink;
use App\Models\Tenant;
use App\Services\Central\Billing\BillingTopupService;
use App\Services\Central\Billing\PaymentLinkService;
use Illuminate\Http\JsonResponse;

/**
 * The payment page API (poachy.com/pay/<token>), called by the web app's server.
 * The link only lets someone pay a shop's Poachy balance; it never reveals the balance.
 */
class PayLinkController extends Controller
{
    public function __construct(private PaymentLinkService $links) {}

    /**
     * @OA\Get(
     *     path="/api/v1/public/pay/{token}",
     *     summary="Payment page data for a pay link",
     *     operationId="publicPayLinkShow",
     *     tags={"Public - Pay"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="shop_name, account_number, paybill_number, suggested_amount, min_amount, expires_at"),
     *     @OA\Response(response=404, description="Unknown link"),
     *     @OA\Response(response=410, description="Expired or already paid (errors.state = expired|used)"),
     *     @OA\Response(response=429, description="Too many requests")
     * )
     */
    public function show(string $token, BillingTopupService $topups): JsonResponse
    {
        $link = PaymentLink::findByToken($token);

        if ($link === null) {
            return ApiResponse::notFound('This payment link is not valid.');
        }

        $state = $this->links->state($link);
        if ($state !== 'valid') {
            return ApiResponse::error($state === 'used' ? 'This payment link has already been used.' : 'This payment link has expired.', ['state' => $state], 410);
        }

        return ApiResponse::success('Payment link', [
            'shop_name' => BusinessDetail::query()->where('tenant_id', $link->tenant_id)->value('business_name') ?? 'Poachy shop',
            'account_number' => (string) Tenant::query()->whereKey($link->tenant_id)->value('mpesa_paybill_account'),
            'paybill_number' => (string) config('messaging.paybill_number'),
            'suggested_amount' => $link->amount === null ? null : (float) $link->amount,
            'min_amount' => $topups->minimumTopup(),
            'max_amount' => BillingTopupService::MAX_STK_AMOUNT,
            'expires_at' => $link->expires_at->toIso8601String(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/public/pay/{token}/stk",
     *     summary="Send an M-Pesa prompt to pay through a pay link",
     *     operationId="publicPayLinkStk",
     *     tags={"Public - Pay"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string", maxLength=100)),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"phone", "amount"},
     *
     *         @OA\Property(property="phone", type="string", example="0712345678"),
     *         @OA\Property(property="amount", type="integer", minimum=10, maximum=150000, example=700)
     *     )),
     *
     *     @OA\Response(response=201, description="Prompt sent; poll GET /pay/{token}/topups/{id}"),
     *     @OA\Response(response=200, description="Same Idempotency-Key: the earlier prompt"),
     *     @OA\Response(response=404, description="Unknown link"),
     *     @OA\Response(response=409, description="A prompt for this link is still open (errors.retry_after seconds)"),
     *     @OA\Response(response=410, description="Expired or already paid"),
     *     @OA\Response(response=422, description="Invalid phone/amount, missing key, or M-Pesa refused"),
     *     @OA\Response(response=429, description="Too many prompts")
     * )
     */
    public function stk(PayLinkStkRequest $request, string $token, BillingTopupService $topups): JsonResponse
    {
        $link = PaymentLink::findByToken($token);

        if ($link === null) {
            return ApiResponse::notFound('This payment link is not valid.');
        }

        $state = $this->links->state($link);
        if ($state !== 'valid') {
            return ApiResponse::error('This payment link can no longer be used.', ['state' => $state], 410);
        }

        $key = 'link'.$link->id.':'.$request->header('Idempotency-Key');
        $existing = BillingTopup::query()->where('idempotency_key', "{$link->tenant_id}:{$key}")->first();

        if ($existing !== null) {
            return ApiResponse::success('Prompt already sent', new BillingTopupResource($existing));
        }

        if ($open = $this->links->openPrompt($link)) {
            $wait = max(1, PaymentLinkService::PROMPT_COOLDOWN_SECONDS - (int) $open->created_at->diffInSeconds(now()));

            return ApiResponse::error('A payment prompt was just sent. Check the phone, or try again shortly.', ['retry_after' => $wait], 409);
        }

        try {
            $topup = $topups->startStk($link->tenant_id, (float) $request->integer('amount'), (string) $request->input('phone'), 'pay_link:'.$link->id, $key, $link->id);
        } catch (MpesaException $e) {
            return ApiResponse::error('M-Pesa could not send the prompt: '.$e->getMessage(), ['error_code' => $e->darajaErrorCode], 422);
        }

        return ApiResponse::success('Check your phone and enter your M-Pesa PIN', new BillingTopupResource($topup), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/public/pay/{token}/topups/{id}",
     *     summary="Status of a prompt sent through a pay link",
     *     operationId="publicPayLinkTopup",
     *     tags={"Public - Pay"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="pending | processing | completed | failed"),
     *     @OA\Response(response=404, description="Unknown link or not this link's prompt")
     * )
     */
    public function topup(string $token, int $id): JsonResponse
    {
        $link = PaymentLink::findByToken($token);
        $topup = $link === null ? null : BillingTopup::query()->where('payment_link_id', $link->id)->find($id);

        return $topup === null
            ? ApiResponse::notFound('Payment not found.')
            : ApiResponse::success('Payment status', new BillingTopupResource($topup));
    }
}
