<?php

namespace App\Http\Controllers\Api\Central\Payouts;

use App\Http\Controllers\Controller;
use App\Services\Central\Payouts\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Safaricom's B2C result / timeout for marketplace payouts. The URL carries a secret derived
 * from the app key (a wrong one is 404, like a missing route); the payout is matched on its
 * ConversationID and a success must report the payout's own amount. Safaricom always gets the
 * acknowledgement it expects.
 */
class PayoutCallbackController extends Controller
{
    public function __construct(private readonly PayoutService $payouts) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/b2c/payout/result/{token}",
     *     summary="M-Pesa B2C result for a marketplace payout (Safaricom → Poachy)",
     *     operationId="marketplacePayoutB2cResult",
     *     tags={"Central - Marketplace - Payouts"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, description="Secret path segment", @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="Result", type="object"))),
     *
     *     @OA\Response(response=200, description="Acknowledged", @OA\JsonContent(@OA\Property(property="ResultCode", type="integer", example=0), @OA\Property(property="ResultDesc", type="string"))),
     *     @OA\Response(response=404, description="Wrong token")
     * )
     */
    public function result(Request $request, string $token): JsonResponse
    {
        $this->guard($token);
        $this->payouts->handleResult($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/mpesa/b2c/payout/timeout/{token}",
     *     summary="M-Pesa B2C queue timeout for a marketplace payout (never re-sent automatically)",
     *     operationId="marketplacePayoutB2cTimeout",
     *     tags={"Central - Marketplace - Payouts"},
     *
     *     @OA\Parameter(name="token", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Acknowledged"),
     *     @OA\Response(response=404, description="Wrong token")
     * )
     */
    public function timeout(Request $request, string $token): JsonResponse
    {
        $this->guard($token);
        $this->payouts->handleTimeout($request->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    private function guard(string $token): void
    {
        abort_unless(hash_equals($this->payouts->callbackToken(), $token), 404);
    }
}
