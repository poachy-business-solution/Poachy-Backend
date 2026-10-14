<?php

namespace App\Http\Controllers\Api\Tenant\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Marketplace\ListReservationsRequest;
use App\Http\Requests\Tenant\Marketplace\RedeemReservationRequest;
use App\Http\Resources\Tenant\Marketplace\ShopReservationResource;
use App\Http\Responses\ApiResponse;
use App\Jobs\Tenant\ProcessInboundReservationHold;
use App\Models\MarketplaceReservation;
use App\Models\Tenant\Sale;
use App\Services\Central\Marketplace\ReservationService;
use App\Services\Tenant\Inventory\StockReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Reserve & collect on the POS: the list for the Marketplace tab, and lookup + redeem on Pay. */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservations,
        private readonly StockReservationService $stockReservations,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reservations",
     *     summary="This shop's reserve & collect holds (Marketplace tab)",
     *     operationId="tenantReservationsIndex",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending","active","failed","redeemed","expired","cancelled"})),
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="reservations[] + pagination + counts{open,redeemed_today}"),
     *     @OA\Response(response=403, description="Needs view-marketplace-sales")
     * )
     */
    public function index(ListReservationsRequest $request): JsonResponse
    {
        $tenantId = (string) tenant()->id;
        $page = $this->reservations->listForShop($tenantId, $request->validated(), (int) ($request->validated()['per_page'] ?? 25));

        return ApiResponse::success('Reservations', [
            'reservations' => ShopReservationResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
            'counts' => [
                'open' => MarketplaceReservation::on('central')->forTenant($tenantId)->open()->where('expires_at', '>', now())->count(),
                'redeemed_today' => MarketplaceReservation::on('central')->forTenant($tenantId)->whereDate('redeemed_at', today())->count(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/reservations/{code}",
     *     summary="Look up a buyer's PCH- code on the Pay screen",
     *     description="Returns the item to put in the cart and whether the code can be redeemed (`redeemable` + `blocker`).",
     *     operationId="tenantReservationsShow",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="PCH-4K7Q")),
     *
     *     @OA\Response(response=200, description="The reservation (even when not redeemable — see blocker)"),
     *     @OA\Response(response=404, description="No such code at this shop")
     * )
     */
    public function show(string $code): JsonResponse
    {
        $reservation = $this->find($code);

        if (! $reservation) {
            return ApiResponse::notFound('No reservation with that code at this shop.');
        }

        return ApiResponse::success('Reservation', new ShopReservationResource($reservation, $this->reservations->redeemBlocker($reservation)));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/reservations/{code}/redeem",
     *     summary="Redeem a PCH- code against the sale just recorded",
     *     description="Links the sale to the reservation and releases the stock hold (the sale already took the stock). Redeeming the same code with the same sale again is a no-op.",
     *     operationId="tenantReservationsRedeem",
     *     tags={"Tenant - Marketplace"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"sale_id"}, @OA\Property(property="sale_id", type="integer"))),
     *
     *     @OA\Response(response=200, description="Redeemed"),
     *     @OA\Response(response=404, description="No such code at this shop"),
     *     @OA\Response(response=409, description="Expired, already redeemed, cancelled, failed or still pending; or the sale is linked to another code")
     * )
     */
    public function redeem(string $code, RedeemReservationRequest $request): JsonResponse
    {
        $reservation = $this->find($code);

        if (! $reservation) {
            return ApiResponse::notFound('No reservation with that code at this shop.');
        }

        $sale = Sale::query()->findOrFail((int) $request->validated()['sale_id']);

        if ($reservation->sale_id === $sale->id) {
            return ApiResponse::success('Reservation already redeemed on this sale', new ShopReservationResource($reservation));
        }

        if ($blocker = $this->reservations->redeemBlocker($reservation)) {
            return ApiResponse::conflict($blocker);
        }

        $tenantId = (string) tenant()->id;
        $linkedElsewhere = MarketplaceReservation::on('central')->forTenant($tenantId)->where('sale_id', $sale->id)->where('id', '!=', $reservation->id)->value('code');

        if ($linkedElsewhere) {
            return ApiResponse::conflict("Sale {$sale->sale_number} is already linked to reservation {$linkedElsewhere}.");
        }

        $redeemed = DB::connection('central')->transaction(function () use ($reservation, $sale) {
            $locked = MarketplaceReservation::on('central')->lockForUpdate()->findOrFail($reservation->id);

            if ($blocker = $this->reservations->redeemBlocker($locked)) {
                throw new \RuntimeException($blocker);
            }

            return $this->reservations->redeem($locked, $sale->id, $sale->sale_number, auth('tenant')->id());
        });

        $this->stockReservations->releaseAllReservationsForReference(ProcessInboundReservationHold::REFERENCE_TYPE, $redeemed->id, "Redeemed on sale {$sale->sale_number}");

        return ApiResponse::success('Reservation redeemed', new ShopReservationResource($redeemed));
    }

    private function find(string $code): ?MarketplaceReservation
    {
        return MarketplaceReservation::on('central')->forTenant((string) tenant()->id)->where('code', strtoupper(trim($code)))->first();
    }
}
