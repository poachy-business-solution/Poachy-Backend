<?php

namespace App\Http\Controllers\Api\Central\Payouts;

use App\Http\Controllers\Controller;
use App\Services\Central\Refunds\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Safaricom's result / timeout for a buyer refund (reversal or B2C). Secret path segment derived
 * from the app key (else 404); the refund is matched on its ConversationID.
 */
class RefundCallbackController extends Controller
{
    public function __construct(private readonly RefundService $refunds) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/refund/result/{token}",
     *     summary="M-Pesa reversal / B2C result for a buyer refund (Safaricom → Poachy)",
     *     operationId="marketplaceRefundMpesaResult",
     *     tags={"Central - Marketplace - Refunds"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Acknowledged"),
     *     @OA\Response(response=404, description="Wrong token")
     * )
     */
    public function result(Request $request, string $token): JsonResponse
    {
        abort_unless(hash_equals($this->refunds->callbackToken(), $token), 404);
        $this->refunds->handleResult($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/refund/timeout/{token}",
     *     summary="M-Pesa queue timeout for a buyer refund (never re-sent automatically)",
     *     operationId="marketplaceRefundMpesaTimeout",
     *     tags={"Central - Marketplace - Refunds"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Acknowledged"),
     *     @OA\Response(response=404, description="Wrong token")
     * )
     */
    public function timeout(Request $request, string $token): JsonResponse
    {
        abort_unless(hash_equals($this->refunds->callbackToken(), $token), 404);
        $this->refunds->handleTimeout($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
