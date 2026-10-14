<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\CreateReservationRequest;
use App\Http\Resources\Central\Marketplace\MarketplaceReservationResource;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceReservation;
use App\Services\Central\Marketplace\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Reserve & collect for buyers: hold an item at a shop for 24 h and pay at the counter. */
class MarketplaceReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservations) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/reservations",
     *     summary="Reserve an item at a shop (24 h hold, pay at the counter)",
     *     description="Creates a PCH- code and asks the shop to hold the stock. Requires an Idempotency-Key header; the same key returns the same reservation. Limits: 10 per item, 5 open holds per buyer, one open hold per item.",
     *     operationId="createReservation",
     *     tags={"Central - Customer - Marketplace - Reservations"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, @OA\Schema(type="string", format="uuid")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="product_slug", type="string", example="mango-juice-1l-da904b63"),
     *         @OA\Property(property="product_id", type="integer", description="Alternative to product_slug"),
     *         @OA\Property(property="quantity", type="number", example=1),
     *         @OA\Property(property="ref", type="string", nullable=true, example="wa", description="Link source for the shop's attribution")
     *     )),
     *
     *     @OA\Response(response=201, description="code, status (pending until the shop confirms), product, shop, expires_at"),
     *     @OA\Response(response=400, description="Missing Idempotency-Key"),
     *     @OA\Response(response=422, description="Not available, out of stock, or too many open holds")
     * )
     */
    public function store(CreateReservationRequest $request): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key');

        if ($key === '' || strlen($key) > 100) {
            return ApiResponse::error('Idempotency-Key header is required to prevent duplicate reservations.', ['missing_header' => 'Idempotency-Key'], 400);
        }

        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $reservation = $this->reservations->create($customer, $request->validated(), $key);
        $reservation->load('shop');

        return ApiResponse::success('Reservation created', new MarketplaceReservationResource($reservation), $reservation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/reservations",
     *     summary="My reservations, newest first",
     *     operationId="listReservations",
     *     tags={"Central - Customer - Marketplace - Reservations"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Response(response=200, description="reservations[] + pagination")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();
        $page = $this->reservations->listForCustomer($customer);

        return ApiResponse::success('Reservations', [
            'reservations' => MarketplaceReservationResource::collection($page->items()),
            'pagination' => ['page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/marketplace/reservations/{code}",
     *     summary="One of my reservations",
     *     operationId="showReservation",
     *     tags={"Central - Customer - Marketplace - Reservations"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string", example="PCH-4K7Q")),
     *
     *     @OA\Response(response=200, description="The reservation"),
     *     @OA\Response(response=404, description="Not yours or unknown")
     * )
     */
    public function show(string $code): JsonResponse
    {
        $reservation = $this->mine($code);

        return $reservation
            ? ApiResponse::success('Reservation', new MarketplaceReservationResource($reservation))
            : ApiResponse::notFound('Reservation not found.');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/reservations/{code}/cancel",
     *     summary="Cancel my reservation (the shop releases the stock)",
     *     operationId="cancelReservation",
     *     tags={"Central - Customer - Marketplace - Reservations"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(name="code", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Cancelled"),
     *     @OA\Response(response=404, description="Not yours or unknown"),
     *     @OA\Response(response=422, description="Already collected, expired or cancelled")
     * )
     */
    public function cancel(string $code): JsonResponse
    {
        $reservation = $this->mine($code);

        if (! $reservation) {
            return ApiResponse::notFound('Reservation not found.');
        }

        return ApiResponse::success('Reservation cancelled', new MarketplaceReservationResource($this->reservations->cancel($reservation)));
    }

    private function mine(string $code): ?MarketplaceReservation
    {
        $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

        return MarketplaceReservation::on('central')->with('shop')
            ->where('customer_id', $customer->id)
            ->where('code', strtoupper(trim($code)))
            ->first();
    }
}
