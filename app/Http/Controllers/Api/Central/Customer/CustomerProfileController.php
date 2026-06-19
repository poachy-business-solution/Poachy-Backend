<?php

namespace App\Http\Controllers\Api\Central\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Customer\Address\StoreAddressRequest;
use App\Http\Requests\Central\Customer\Address\UpdateAddressRequest;
use App\Http\Requests\Central\Customer\Auth\UpdateProfilePictureRequest;
use App\Http\Requests\Central\Customer\Auth\UpdateProfileRequest;
use App\Http\Resources\Central\Customer\CustomerAddressResource;
use App\Http\Resources\Central\Customer\CustomerResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Customer\CustomerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProfileController extends Controller
{
    public function __construct(
        private readonly CustomerProfileService $profileService,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/customer/profile",
     *     summary="Get authenticated customer profile",
     *     description="Retrieves the complete profile information for the currently authenticated customer including personal details, verification status, and preferences.",
     *     operationId="getCustomerProfile",
     *     tags={"Central - Customer - Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_number", type="string", example="MKT-CUST-000001"),
     *                 @OA\Property(property="name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="email", type="string", example="richard.hensley@gmail.com"),
     *                 @OA\Property(property="email_verified", type="boolean", example=false),
     *                 @OA\Property(property="phone", type="string", example="+254756789099"),
     *                 @OA\Property(property="phone_verified", type="boolean", example=false),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="2001-02-10"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(property="profile_picture", type="string", nullable=true, example=null),
     *                 @OA\Property(property="accepts_marketing", type="boolean", example=true),
     *                 @OA\Property(property="accepts_sms", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", example="2026-02-16T07:59:16.000000Z"),
     *                 @OA\Property(property="member_since", type="string", format="date", example="2026-02-16")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:18:03.294174Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="8111b02e-e77b-4b5f-a61a-abc2f2966966"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
    public function profile(): JsonResponse
    {
        $customer = auth('central')->user()->marketplaceCustomer->load('user');

        return ApiResponse::success(
            'Profile retrieved successfully.',
            new CustomerResource($customer),
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/central/customer/profile",
     *     summary="Update customer profile information",
     *     description="Updates the authenticated customer's profile information. Only provided fields will be updated. Email updates require uniqueness validation. Customer must be authenticated.",
     *     operationId="updateCustomerProfile",
     *     tags={"Central - Customer - Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Profile fields to update (all fields are optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Customer's full name",
     *                 example="Richard Hensley",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 description="Customer's email address (must be unique)",
     *                 example="richard.hensley@gmail.com",
     *                 maxLength=150
     *             ),
     *             @OA\Property(
     *                 property="phone",
     *                 type="string",
     *                 description="Customer's phone number",
     *                 example="+254756789099",
     *                 maxLength=20
     *             ),
     *             @OA\Property(
     *                 property="date_of_birth",
     *                 type="string",
     *                 format="date",
     *                 description="Customer's date of birth (must be before today)",
     *                 example="1990-01-01",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="gender",
     *                 type="string",
     *                 description="Customer's gender",
     *                 enum={"male", "female", "other", "prefer_not_to_say"},
     *                 example="male",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="accepts_marketing",
     *                 type="boolean",
     *                 description="Whether customer accepts marketing communications",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="accepts_sms",
     *                 type="boolean",
     *                 description="Whether customer accepts SMS communications",
     *                 example=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_number", type="string", example="MKT-CUST-000001"),
     *                 @OA\Property(property="name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="email", type="string", example="richard.hensley@gmail.com"),
     *                 @OA\Property(property="email_verified", type="boolean", example=false),
     *                 @OA\Property(property="phone", type="string", example="+254756789099"),
     *                 @OA\Property(property="phone_verified", type="boolean", example=false),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(property="profile_picture", type="string", nullable=true, example=null),
     *                 @OA\Property(property="accepts_marketing", type="boolean", example=true),
     *                 @OA\Property(property="accepts_sms", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", example="2026-02-16T07:59:16.000000Z"),
     *                 @OA\Property(property="member_since", type="string", format="date", example="2026-02-16")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:21:41.471867Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b1e87d68-3899-4b5f-95a9-374191780728"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $customer = $this->profileService->updateProfile(
            auth('central')->user(),
            $request->validated(),
        );

        return ApiResponse::success(
            'Profile updated successfully.',
            new CustomerResource($customer),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/customer/profile/picture",
     *     summary="Upload or replace profile picture",
     *     description="Uploads a new profile picture for the authenticated customer. Replaces existing profile picture if one exists. Accepts JPG, JPEG, PNG, and WEBP formats with a maximum file size of 2MB.",
     *     operationId="uploadProfilePicture",
     *     tags={"Central - Customer - Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_picture"},
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Image file — JPG, JPEG, PNG or WEBP, max 2 MB"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile picture updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="customer_number", type="string", example="MKT-CUST-000001"),
     *                 @OA\Property(property="name", type="string", example="Richard Hensley"),
     *                 @OA\Property(property="email", type="string", example="richard.hensley@gmail.com"),
     *                 @OA\Property(property="email_verified", type="boolean", example=true),
     *                 @OA\Property(property="phone", type="string", example="+254756789099"),
     *                 @OA\Property(property="phone_verified", type="boolean", example=false),
     *                 @OA\Property(property="date_of_birth", type="string", format="date", example="1990-01-01"),
     *                 @OA\Property(property="gender", type="string", example="male"),
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="uri",
     *                     example="http://localhost/storage/marketplace/customers/avatars/426ad14e-d84a-4b0d-ae91-5f37775b43df.jpg"
     *                 ),
     *                 @OA\Property(property="accepts_marketing", type="boolean", example=true),
     *                 @OA\Property(property="accepts_sms", type="boolean", example=true),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time", example="2026-02-16T09:35:50.000000Z"),
     *                 @OA\Property(property="member_since", type="string", format="date", example="2026-02-16")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T09:40:37.874642Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e46e5585-5ac3-4dc0-bdb2-3b673cfc7802"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
     *         description="Validation error - invalid file type or size",
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
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        $customer = $this->profileService->updateProfilePicture(
            auth('central')->user(),
            $request->file('profile_picture'),
        );

        return ApiResponse::success(
            'Profile picture updated successfully.',
            new CustomerResource($customer),
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/customer/delivery-addresses",
     *     summary="Get all delivery addresses for authenticated customer",
     *     description="Retrieves a list of all delivery addresses saved by the authenticated customer. Includes address details, recipient information, coordinates, and delivery instructions.",
     *     operationId="listDeliveryAddresses",
     *     tags={"Central - Customer - Delivery Addresses"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Delivery addresses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery addresses retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="address_type",
     *                         type="string",
     *                         enum={"home", "work", "other"},
     *                         example="home"
     *                     ),
     *                     @OA\Property(property="label", type="string", example="My Main House"),
     *                     @OA\Property(property="recipient_name", type="string", example="Jane Doe"),
     *                     @OA\Property(property="recipient_phone", type="string", example="+254745548091"),
     *                     @OA\Property(property="address_line", type="string", example="123 Maple Avenue"),
     *                     @OA\Property(property="building_apartment", type="string", example="Suite 4B"),
     *                     @OA\Property(property="city", type="string", example="Springfield"),
     *                     @OA\Property(property="county", type="string", example="Lincoln County"),
     *                     @OA\Property(property="postal_code", type="string", example="62704"),
     *                     @OA\Property(
     *                         property="coordinates",
     *                         type="object",
     *                         @OA\Property(property="latitude", type="number", format="float", example=39.7817),
     *                         @OA\Property(property="longitude", type="number", format="float", example=-89.6501)
     *                     ),
     *                     @OA\Property(
     *                         property="delivery_instructions",
     *                         type="string",
     *                         example="Please leave the package behind the large planter near the front door. Ring the bell twice."
     *                     ),
     *                     @OA\Property(property="is_default", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-16T08:28:08.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:28:47.292077Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="520e8e3d-a069-4c09-ae9e-a12697f625f3"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
    public function addresses(): JsonResponse
    {
        $customer   = auth('central')->user()->marketplaceCustomer;
        $addresses  = $this->profileService->getAddresses($customer);

        return ApiResponse::success(
            'Delivery addresses retrieved successfully.',
            CustomerAddressResource::collection($addresses),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/customer/delivery-addresses",
     *     summary="Add a new delivery address",
     *     description="Creates a new delivery address for the authenticated customer. The address can be marked as default, which will automatically unset any previously default address. Coordinates (latitude/longitude) are optional but recommended for accurate delivery.",
     *     operationId="createDeliveryAddress",
     *     tags={"Central - Customer - Delivery Addresses"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Delivery address data",
     *         @OA\JsonContent(
     *             required={"recipient_name", "recipient_phone", "address_line", "city", "county"},
     *             @OA\Property(
     *                 property="address_type",
     *                 type="string",
     *                 enum={"home", "work", "other"},
     *                 description="Type of address",
     *                 example="home",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="label",
     *                 type="string",
     *                 description="Custom label for the address",
     *                 example="My Main House",
     *                 maxLength=50,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="recipient_name",
     *                 type="string",
     *                 description="Name of the person receiving deliveries",
     *                 example="Jane Doe",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="recipient_phone",
     *                 type="string",
     *                 description="Phone number of the recipient",
     *                 example="0745548091",
     *                 maxLength=20
     *             ),
     *             @OA\Property(
     *                 property="address_line",
     *                 type="string",
     *                 description="Street address",
     *                 example="123 Maple Avenue",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="building_apartment",
     *                 type="string",
     *                 description="Building name, apartment number, or suite",
     *                 example="Suite 4B",
     *                 maxLength=100,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="city",
     *                 type="string",
     *                 description="City name",
     *                 example="Springfield",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="county",
     *                 type="string",
     *                 description="County or region",
     *                 example="Lincoln County",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="postal_code",
     *                 type="string",
     *                 description="Postal or ZIP code",
     *                 example="62704",
     *                 maxLength=20,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="latitude",
     *                 type="number",
     *                 format="float",
     *                 description="Latitude coordinate (-90 to 90)",
     *                 example=39.7817,
     *                 minimum=-90,
     *                 maximum=90,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="longitude",
     *                 type="number",
     *                 format="float",
     *                 description="Longitude coordinate (-180 to 180)",
     *                 example=-89.6501,
     *                 minimum=-180,
     *                 maximum=180,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="delivery_instructions",
     *                 type="string",
     *                 description="Special delivery instructions",
     *                 example="Please leave the package behind the large planter near the front door. Ring the bell twice.",
     *                 maxLength=500,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="is_default",
     *                 type="boolean",
     *                 description="Set as default delivery address",
     *                 example=true,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Delivery address added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery address added successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="address_type", type="string", example="home"),
     *                 @OA\Property(property="label", type="string", example="My Main House"),
     *                 @OA\Property(property="recipient_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="recipient_phone", type="string", example="+254745548091"),
     *                 @OA\Property(property="address_line", type="string", example="123 Maple Avenue"),
     *                 @OA\Property(property="building_apartment", type="string", example="Suite 4B"),
     *                 @OA\Property(property="city", type="string", example="Springfield"),
     *                 @OA\Property(property="county", type="string", example="Lincoln County"),
     *                 @OA\Property(property="postal_code", type="string", example="62704"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="number", format="float", example=39.7817),
     *                     @OA\Property(property="longitude", type="number", format="float", example=-89.6501)
     *                 ),
     *                 @OA\Property(
     *                     property="delivery_instructions",
     *                     type="string",
     *                     example="Please leave the package behind the large planter near the front door. Ring the bell twice."
     *                 ),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-16T08:28:08.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:28:08.174170Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="245375ab-7216-40a7-830b-9ef3ff33ad4d"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function storeAddress(StoreAddressRequest $request): JsonResponse
    {
        $customer = auth('central')->user()->marketplaceCustomer;
        $address  = $this->profileService->createAddress($customer, $request->validated());

        return ApiResponse::created(
            'Delivery address added successfully.',
            new CustomerAddressResource($address),
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/central/customer/delivery-addresses/{id}",
     *     summary="Update an existing delivery address",
     *     description="Updates an existing delivery address for the authenticated customer. Only provided fields will be updated. All fields are optional in the request body.",
     *     operationId="updateDeliveryAddress",
     *     tags={"Central - Customer - Delivery Addresses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery address ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Fields to update (all optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="address_type",
     *                 type="string",
     *                 enum={"home", "work", "other"},
     *                 description="Type of address",
     *                 example="home",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="label",
     *                 type="string",
     *                 description="Custom label for the address",
     *                 example="My Main House",
     *                 maxLength=50,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="recipient_name",
     *                 type="string",
     *                 description="Name of the person receiving deliveries",
     *                 example="Jane Doe",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="recipient_phone",
     *                 type="string",
     *                 description="Phone number of the recipient",
     *                 example="+254745548091",
     *                 maxLength=20
     *             ),
     *             @OA\Property(
     *                 property="address_line",
     *                 type="string",
     *                 description="Street address",
     *                 example="123 Silver Road",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="building_apartment",
     *                 type="string",
     *                 description="Building name, apartment number, or suite",
     *                 example="Suite A508",
     *                 maxLength=100,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="city",
     *                 type="string",
     *                 description="City name",
     *                 example="Springfield",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="county",
     *                 type="string",
     *                 description="County or region",
     *                 example="Lincoln County",
     *                 maxLength=100
     *             ),
     *             @OA\Property(
     *                 property="postal_code",
     *                 type="string",
     *                 description="Postal or ZIP code",
     *                 example="62704",
     *                 maxLength=20,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="latitude",
     *                 type="number",
     *                 format="float",
     *                 description="Latitude coordinate (-90 to 90)",
     *                 example=39.7817,
     *                 minimum=-90,
     *                 maximum=90,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="longitude",
     *                 type="number",
     *                 format="float",
     *                 description="Longitude coordinate (-180 to 180)",
     *                 example=-89.6501,
     *                 minimum=-180,
     *                 maximum=180,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="delivery_instructions",
     *                 type="string",
     *                 description="Special delivery instructions",
     *                 example="Please leave the package behind the large planter near the front door. Ring the bell twice.",
     *                 maxLength=500,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="is_default",
     *                 type="boolean",
     *                 description="Set as default delivery address",
     *                 example=true,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery address updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery address updated successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="address_type", type="string", example="home"),
     *                 @OA\Property(property="label", type="string", example="My Main House"),
     *                 @OA\Property(property="recipient_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="recipient_phone", type="string", example="+254745548091"),
     *                 @OA\Property(property="address_line", type="string", example="123 Silver Road"),
     *                 @OA\Property(property="building_apartment", type="string", example="Suite A508"),
     *                 @OA\Property(property="city", type="string", example="Springfield"),
     *                 @OA\Property(property="county", type="string", example="Lincoln County"),
     *                 @OA\Property(property="postal_code", type="string", example="62704"),
     *                 @OA\Property(
     *                     property="coordinates",
     *                     type="object",
     *                     @OA\Property(property="latitude", type="number", format="float", example=39.7817),
     *                     @OA\Property(property="longitude", type="number", format="float", example=-89.6501)
     *                 ),
     *                 @OA\Property(
     *                     property="delivery_instructions",
     *                     type="string",
     *                     example="Please leave the package behind the large planter near the front door. Ring the bell twice."
     *                 ),
     *                 @OA\Property(property="is_default", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-02-16T08:28:08.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:31:52.264972Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="554c9dcf-5254-48d7-89bd-3914291bbd3d"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
     *         description="Delivery address not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery address not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
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
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function updateAddress(UpdateAddressRequest $request, int $id): JsonResponse
    {
        $customer = auth('central')->user()->marketplaceCustomer;
        $address  = $this->profileService->updateAddress($customer, $id, $request->validated());

        return ApiResponse::success(
            'Delivery address updated successfully.',
            new CustomerAddressResource($address),
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/central/customer/delivery-addresses/{id}",
     *     summary="Delete a delivery address",
     *     description="Deletes a delivery address for the authenticated customer. Cannot delete the only remaining delivery address - customer must have at least one address on file.",
     *     operationId="deleteDeliveryAddress",
     *     tags={"Central - Customer - Delivery Addresses"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Delivery address ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery address deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Delivery address deleted successfully."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:35:30.221185Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="12704a45-6f64-4789-b559-fdc8363da447"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
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
     *         description="Delivery address not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Delivery address not found."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - cannot delete only address",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="address",
     *                     type="array",
     *                     @OA\Items(type="string", example="You cannot delete your only delivery address.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-16T08:33:57.208473Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7532c47f-369f-47cf-a7e8-fd5200cc5b94"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function deleteAddress(int $id): JsonResponse
    {
        $customer = auth('central')->user()->marketplaceCustomer;
        $this->profileService->deleteAddress($customer, $id);

        return ApiResponse::success('Delivery address deleted successfully.');
    }
}
