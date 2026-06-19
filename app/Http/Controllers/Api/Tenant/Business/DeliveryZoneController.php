<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Business\StoreDeliveryZoneRequest;
use App\Http\Requests\Tenant\Business\UpdateDeliveryZoneRequest;
use App\Http\Resources\Tenant\Business\DeliveryZoneResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Business\DeliveryZoneService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function __construct(
        private readonly DeliveryZoneService $deliveryZoneService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-details/delivery-zones",
     *     summary="List tenant delivery zones",
     *     description="Retrieves all delivery zones configured by the tenant. Supports filtering by active status, zone type, and search. Zones define delivery coverage areas (city, county, postal code, or radius-based) with associated fees, delivery times, and supported methods. Requires tenant authentication.",
     *     operationId="listDeliveryZones",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="zone_type",
     *         in="query",
     *         description="Filter by zone type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"city", "county", "postal_code", "radius"}, example="city")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by zone name",
     *         required=false,
     *         @OA\Schema(type="string", example="Nairobi")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zones retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zones retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="zone_name", type="string", example="Nairobi City Zone"),
     *                     @OA\Property(property="zone_type", type="string", enum={"city", "county", "postal_code", "radius"}, example="city"),
     *                     @OA\Property(property="cities", type="array", nullable=true, @OA\Items(type="string", example="nairobi")),
     *                     @OA\Property(property="counties", type="array", nullable=true, @OA\Items(type="string", example="kiambu")),
     *                     @OA\Property(property="postal_codes", type="array", nullable=true, @OA\Items(type="string", example="00100")),
     *                     @OA\Property(property="latitude", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="longitude", type="number", format="float", nullable=true, example=null),
     *                     @OA\Property(property="radius_km", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="standard_fee", type="string", example="200.00"),
     *                     @OA\Property(property="express_fee", type="string", nullable=true, example="350.00"),
     *                     @OA\Property(property="scheduled_fee", type="string", nullable=true, example="150.00"),
     *                     @OA\Property(property="free_delivery_threshold", type="string", nullable=true, example="5000.00"),
     *                     @OA\Property(property="standard_delivery_time", type="string", nullable=true, example="2-3 hours"),
     *                     @OA\Property(property="express_delivery_time", type="string", nullable=true, example="1 hour"),
     *                     @OA\Property(property="scheduled_delivery_time", type="string", nullable=true, example="Same day"),
     *                     @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", enum={"standard", "express", "scheduled"}, example="standard")),
     *                     @OA\Property(property="priority", type="integer", example=1),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T10:59:10.994289Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="48b365ab-6a2e-4024-99b2-d9ec76c2ca74"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $zones = $this->deliveryZoneService->getAll($request->only(['is_active', 'zone_type', 'search']));

        return ApiResponse::success(
            'Delivery zones retrieved successfully',
            DeliveryZoneResource::collection($zones),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/business-details/delivery-zones",
     *     summary="Create a new delivery zone",
     *     description="Creates a new delivery zone for the tenant. Supports four zone types: city (list of cities), county (list of counties), postal_code (list of postal codes), or radius (coordinates with radius). Each zone defines delivery fees, supported methods, and estimated times. Fee required for each supported method. Requires tenant authentication.",
     *     operationId="createDeliveryZone",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Delivery zone configuration",
     *         @OA\JsonContent(
     *             required={"zone_name", "zone_type", "standard_fee", "supported_methods"},
     *             @OA\Property(property="zone_name", type="string", example="Nairobi City Zone", maxLength=100),
     *             @OA\Property(property="zone_type", type="string", enum={"city", "county", "postal_code", "radius"}, example="city"),
     *             @OA\Property(property="cities", type="array", description="Required if zone_type is 'city'", @OA\Items(type="string", maxLength=100, example="Nairobi"), nullable=true),
     *             @OA\Property(property="counties", type="array", description="Required if zone_type is 'county'", @OA\Items(type="string", maxLength=100, example="Kiambu"), nullable=true),
     *             @OA\Property(property="postal_codes", type="array", description="Required if zone_type is 'postal_code'", @OA\Items(type="string", maxLength=20, example="00100"), nullable=true),
     *             @OA\Property(property="latitude", type="number", format="float", description="Required if zone_type is 'radius'", minimum=-90, maximum=90, example=-1.286389, nullable=true),
     *             @OA\Property(property="longitude", type="number", format="float", description="Required if zone_type is 'radius'", minimum=-180, maximum=180, example=36.817223, nullable=true),
     *             @OA\Property(property="radius_km", type="integer", description="Required if zone_type is 'radius'", minimum=1, maximum=500, example=10, nullable=true),
     *             @OA\Property(property="standard_fee", type="number", format="float", example=200, minimum=0),
     *             @OA\Property(property="express_fee", type="number", format="float", example=350, minimum=0, nullable=true),
     *             @OA\Property(property="scheduled_fee", type="number", format="float", example=150, minimum=0, nullable=true),
     *             @OA\Property(property="free_delivery_threshold", type="number", format="float", description="Order amount for free delivery", example=5000, minimum=0, nullable=true),
     *             @OA\Property(property="standard_delivery_time", type="string", example="2-3 hours", maxLength=100, nullable=true),
     *             @OA\Property(property="express_delivery_time", type="string", example="1 hour", maxLength=100, nullable=true),
     *             @OA\Property(property="scheduled_delivery_time", type="string", example="Same day", maxLength=100, nullable=true),
     *             @OA\Property(property="supported_methods", type="array", description="Must include at least 'standard'", @OA\Items(type="string", enum={"standard", "express", "scheduled"}, example="standard"), minItems=1),
     *             @OA\Property(property="priority", type="integer", description="Lower numbers = higher priority", minimum=1, maximum=999, example=1, nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Delivery zone created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zone created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="zone_name", type="string", example="Nairobi City Zone"),
     *                 @OA\Property(property="zone_type", type="string", example="city"),
     *                 @OA\Property(property="cities", type="array", @OA\Items(type="string", example="nairobi")),
     *                 @OA\Property(property="counties", type="array", nullable=true, @OA\Items(type="string", example="kiambu")),
     *                 @OA\Property(property="postal_codes", type="array", nullable=true, @OA\Items(type="string", example="00100")),
     *                 @OA\Property(property="latitude", type="number", nullable=true, example=null),
     *                 @OA\Property(property="longitude", type="number", nullable=true, example=null),
     *                 @OA\Property(property="radius_km", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="standard_fee", type="string", example="200.00"),
     *                 @OA\Property(property="express_fee", type="string", example="350.00"),
     *                 @OA\Property(property="scheduled_fee", type="string", example="150.00"),
     *                 @OA\Property(property="free_delivery_threshold", type="string", example="5000.00"),
     *                 @OA\Property(property="standard_delivery_time", type="string", example="2-3 hours"),
     *                 @OA\Property(property="express_delivery_time", type="string", example="1 hour"),
     *                 @OA\Property(property="scheduled_delivery_time", type="string", example="Same day"),
     *                 @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", example="standard")),
     *                 @OA\Property(property="priority", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T10:56:34.821010Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="db88985a-750c-47fd-acc6-4ea336b73b99"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - e.g., missing fee for supported method",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="scheduled_fee",
     *                     type="array",
     *                     @OA\Items(type="string", example="Scheduled delivery fee is required when scheduled delivery is a supported method.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T10:58:11.258957Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="70d2296d-bde9-47c6-b0cf-eb0bdabbe62a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function store(StoreDeliveryZoneRequest $request): JsonResponse
    {
        $zone = $this->deliveryZoneService->store($request->validated());

        return ApiResponse::created(
            'Delivery zone created successfully',
            new DeliveryZoneResource($zone),
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-details/delivery-zones/{id}",
     *     summary="Get single delivery zone details",
     *     description="Retrieves detailed information about a specific delivery zone. Requires tenant authentication.",
     *     operationId="getDeliveryZone",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery zone ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zone retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zone retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="zone_name", type="string", example="Nairobi City Zone"),
     *                 @OA\Property(property="zone_type", type="string", example="city"),
     *                 @OA\Property(property="cities", type="array", nullable=true, @OA\Items(type="string", example="nairobi")),
     *                 @OA\Property(property="counties", type="array", nullable=true, @OA\Items(type="string", example="kiambu")),
     *                 @OA\Property(property="postal_codes", type="array", nullable=true, @OA\Items(type="string", example="00100")),
     *                 @OA\Property(property="latitude", type="number", format="float", nullable=true, example=null),
     *                 @OA\Property(property="longitude", type="number", format="float", nullable=true, example=null),
     *                 @OA\Property(property="radius_km", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="standard_fee", type="string", example="200.00"),
     *                 @OA\Property(property="express_fee", type="string", nullable=true, example="350.00"),
     *                 @OA\Property(property="scheduled_fee", type="string", nullable=true, example="150.00"),
     *                 @OA\Property(property="free_delivery_threshold", type="string", nullable=true, example="5000.00"),
     *                 @OA\Property(property="standard_delivery_time", type="string", nullable=true, example="2-3 hours"),
     *                 @OA\Property(property="express_delivery_time", type="string", nullable=true, example="1 hour"),
     *                 @OA\Property(property="scheduled_delivery_time", type="string", nullable=true, example="Same day"),
     *                 @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", example="standard")),
     *                 @OA\Property(property="priority", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T11:07:53.434147Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="95dbf9ad-be44-4f79-aa94-e5acb164ab51"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery zone not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery zone not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        try {
            $zone = $this->deliveryZoneService->findOrFail($id);
        } catch (ModelNotFoundException) {
            return ApiResponse::notFound('Delivery zone not found');
        }

        return ApiResponse::success(
            'Delivery zone retrieved successfully',
            new DeliveryZoneResource($zone),
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/delivery-zones/{id}",
     *     summary="Update delivery zone",
     *     description="Updates an existing delivery zone. All fields are optional - only provided fields will be updated. Requires tenant authentication.",
     *     operationId="updateDeliveryZone",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery zone ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(property="zone_name", type="string", maxLength=100, example="Updated Zone Name"),
     *             @OA\Property(property="zone_type", type="string", enum={"city", "county", "postal_code", "radius"}, example="city"),
     *             @OA\Property(property="cities", type="array", @OA\Items(type="string", maxLength=100, example="Kilimani"), nullable=true),
     *             @OA\Property(property="counties", type="array", @OA\Items(type="string", maxLength=100), nullable=true),
     *             @OA\Property(property="postal_codes", type="array", @OA\Items(type="string", maxLength=20), nullable=true),
     *             @OA\Property(property="latitude", type="number", format="float", minimum=-90, maximum=90, nullable=true),
     *             @OA\Property(property="longitude", type="number", format="float", minimum=-180, maximum=180, nullable=true),
     *             @OA\Property(property="radius_km", type="integer", minimum=1, maximum=500, nullable=true),
     *             @OA\Property(property="standard_fee", type="number", format="float", minimum=0),
     *             @OA\Property(property="express_fee", type="number", format="float", minimum=0, nullable=true),
     *             @OA\Property(property="scheduled_fee", type="number", format="float", minimum=0, nullable=true),
     *             @OA\Property(property="free_delivery_threshold", type="number", format="float", minimum=0, nullable=true),
     *             @OA\Property(property="standard_delivery_time", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="express_delivery_time", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="scheduled_delivery_time", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", enum={"standard", "express", "scheduled"}), minItems=1),
     *             @OA\Property(property="priority", type="integer", minimum=1, maximum=999),
     *             @OA\Property(property="is_active", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zone updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zone updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="zone_name", type="string", example="Nairobi City Zone"),
     *                 @OA\Property(property="zone_type", type="string", example="city"),
     *                 @OA\Property(property="cities", type="array", @OA\Items(type="string", example="kilimani")),
     *                 @OA\Property(property="counties", type="array", nullable=true, @OA\Items(type="string", example="kiambu")),
     *                 @OA\Property(property="postal_codes", type="array", nullable=true, @OA\Items(type="string", example="00100")),
     *                 @OA\Property(property="latitude", type="number", nullable=true, example=null),
     *                 @OA\Property(property="longitude", type="number", nullable=true, example=null),
     *                 @OA\Property(property="radius_km", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="standard_fee", type="string", example="200.00"),
     *                 @OA\Property(property="express_fee", type="string", example="350.00"),
     *                 @OA\Property(property="scheduled_fee", type="string", example="150.00"),
     *                 @OA\Property(property="free_delivery_threshold", type="string", example="5000.00"),
     *                 @OA\Property(property="standard_delivery_time", type="string", example="2-3 hours"),
     *                 @OA\Property(property="express_delivery_time", type="string", example="1 hour"),
     *                 @OA\Property(property="scheduled_delivery_time", type="string", example="Same day"),
     *                 @OA\Property(property="supported_methods", type="array", @OA\Items(type="string", example="standard")),
     *                 @OA\Property(property="priority", type="integer", example=1),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-26T10:56:34.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-02-26T11:11:51.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T11:11:51.599694Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="426d62df-6691-4e54-8515-45b525799019"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery zone not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery zone not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateDeliveryZoneRequest $request, int $id): JsonResponse
    {
        try {
            $zone = $this->deliveryZoneService->findOrFail($id);
        } catch (ModelNotFoundException) {
            return ApiResponse::notFound('Delivery zone not found');
        }

        $updated = $this->deliveryZoneService->update($zone, $request->validated());

        return ApiResponse::success(
            'Delivery zone updated successfully',
            new DeliveryZoneResource($updated),
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/business-details/delivery-zones/{id}",
     *     summary="Delete delivery zone",
     *     description="Permanently deletes a delivery zone. Requires tenant authentication.",
     *     operationId="deleteDeliveryZone",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery zone ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery zone deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery zone deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T11:19:20.048717Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f2a3155e-aeef-4e32-9e42-6df416b3734c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Delivery zone not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery zone not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $zone = $this->deliveryZoneService->findOrFail($id);
        } catch (ModelNotFoundException) {
            return ApiResponse::notFound('Delivery zone not found');
        }

        $this->deliveryZoneService->destroy($zone);

        return ApiResponse::success('Delivery zone deleted successfully');
    }

    /** 
     * @OA\Post(
     *     path="/api/v1/tenant/business-details/delivery-zones/reorder",
     *     summary="Reorder delivery zone priorities",
     *     description="Updates the priority order of multiple delivery zones in a single operation. Lower priority numbers are matched first when determining delivery zones for customer addresses. Requires tenant authentication.",
     *     operationId="reorderDeliveryZones",
     *     tags={"Tenant - Delivery Zones"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Zone IDs with new priorities",
     *         @OA\JsonContent(
     *             required={"zones"},
     *             @OA\Property(
     *                 property="zones",
     *                 type="array",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"id", "priority"},
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="priority", type="integer", minimum=1, maximum=999, example=2)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Zone priorities updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Zone priorities updated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-26T11:36:52.129108Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="acd3a848-d59f-4288-9152-5d78adfac3d0"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 description="Validation errors keyed by field name",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {"type": "string"}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'zones'            => ['required', 'array', 'min:1'],
            'zones.*.id'       => ['required', 'integer'],
            'zones.*.priority' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $this->deliveryZoneService->reorder($request->zones);

        return ApiResponse::success('Zone priorities updated successfully');
    }
}
