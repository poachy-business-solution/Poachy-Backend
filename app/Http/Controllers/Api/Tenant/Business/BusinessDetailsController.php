<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Business\SubmitBusinessDetailsRequest;
use App\Http\Requests\Tenant\Business\UpdateDeliveryInfoRequest;
use App\Http\Requests\Tenant\Business\UpdateLocationRequest;
use App\Http\Requests\Tenant\Business\UpdateMediaRequest;
use App\Http\Requests\Tenant\Business\UpdateOperatingHoursRequest;
use App\Http\Requests\Tenant\Business\UpdateProfileRequest;
use App\Http\Requests\Tenant\Business\UpdateSettingsRequest;
use App\Http\Requests\Tenant\Business\UpdateSocialMediaRequest;
use App\Http\Resources\Central\Admin\Tenant\BusinessDetailResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Business\BusinessDetailsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BusinessDetailsController extends Controller
{
    public function __construct(
        private readonly BusinessDetailsService $businessDetailsService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/business-details",
     *     summary="Submit business details for approval",
     *     description="Tenant submits comprehensive business details including logo, banner, and operational information for admin approval (stored in central DB)",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"business_name", "business_type_id", "business_category_id", "business_phone"},
     *                 @OA\Property(property="business_name", type="string", maxLength=255, example="Tech Haven Electronics", description="Official business name"),
     *                 @OA\Property(property="business_description", type="string", maxLength=1000, example="Leading electronics retailer offering the latest gadgets, computers, and accessories with competitive prices and excellent customer service.", description="Brief description of the business"),
     *                 @OA\Property(property="business_logo", type="string", format="binary", description="Business logo image (JPEG, PNG, JPG, WEBP - Max 2MB)"),
     *                 @OA\Property(property="business_banner", type="string", format="binary", description="Business banner image (JPEG, PNG, JPG, WEBP - Max 5MB)"),
     *                 @OA\Property(property="business_type_id", type="integer", example=1, description="ID from business_types table"),
     *                 @OA\Property(property="business_category_id", type="integer", example=3, description="ID from business_categories table"),
     *                 @OA\Property(property="business_email", type="string", format="email", maxLength=255, example="info@techhaven.co.ke", description="Business contact email"),
     *                 @OA\Property(property="business_phone", type="string", maxLength=20, example="+254712345678", description="Business contact phone number"),
     *                 @OA\Property(property="contact_person", type="string", maxLength=255, example="John Doe", description="Primary contact person name"),
     *                 @OA\Property(property="address", type="string", maxLength=500, example="Westlands Mall, Ground Floor, Shop G12", description="Physical business address"),
     *                 @OA\Property(property="city", type="string", maxLength=100, example="Nairobi", description="City where business is located"),
     *                 @OA\Property(property="county", type="string", maxLength=100, example="Nairobi", description="County/region where business is located"),
     *                 @OA\Property(
     *                     property="operating_hours",
     *                     type="object",
     *                     description="Weekly operating hours with open/close times in HH:MM format",
     *                     example={
     *                         "monday": {"open": "08:00", "close": "20:00"},
     *                         "tuesday": {"open": "08:00", "close": "20:00"},
     *                         "wednesday": {"open": "08:00", "close": "20:00"},
     *                         "thursday": {"open": "08:00", "close": "20:00"},
     *                         "friday": {"open": "08:00", "close": "20:00"},
     *                         "saturday": {"open": "09:00", "close": "18:00"},
     *                         "sunday": {"open": "10:00", "close": "16:00"}
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="delivery_info",
     *                     type="object",
     *                     description="Delivery availability. Fees and areas are managed via delivery zones.",
     *                     example={
     *                         "available": true
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="settings",
     *                     type="object",
     *                     description="Business settings and preferences",
     *                     example={
     *                         "currency": "KES",
     *                         "payment_methods": {"cash", "mpesa", "card", "bank_transfer"}
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="social_media",
     *                     type="object",
     *                     description="Social media handles and links",
     *                     example={
     *                         "facebook": "https://facebook.com/techhaven",
     *                         "instagram": "@techhaven_ke",
     *                         "twitter": "@TechHavenKE",
     *                         "whatsapp": "+254712345678"
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Business details submitted successfully and awaiting admin approval",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business details submitted successfully. Awaiting admin approval."),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="business_name", type="string", example="Tech Haven Electronics"),
     *                 @OA\Property(property="business_description", type="string", example="Leading electronics retailer offering the latest gadgets, computers, and accessories with competitive prices and excellent customer service."),
     *                 @OA\Property(property="business_logo", type="string", format="url", example="http://techhaven.localhost/tenancy/assets/storage/business/logos/kXFKctcSe15MG22BBeRy92dqX9im9QAOouXYEsVa.jpg", description="Full URL to uploaded logo"),
     *                 @OA\Property(property="business_banner", type="string", format="url", example="http://techhaven.localhost/tenancy/assets/storage/business/banners/nP5ALnff2HDgfVF8ePkOJ7qlFjoVZP4HPLlNJCpp.jpg", description="Full URL to uploaded banner"),
     *                 @OA\Property(
     *                     property="business_type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Retail & Consumer Goods"),
     *                     @OA\Property(property="slug", type="string", example="retail-consumer-goods")
     *                 ),
     *                 @OA\Property(
     *                     property="business_category",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Electronics Shop"),
     *                     @OA\Property(property="slug", type="string", example="electronics-shop")
     *                 ),
     *                 @OA\Property(property="business_email", type="string", example="info@techhaven.co.ke"),
     *                 @OA\Property(property="business_phone", type="string", example="+254712345678"),
     *                 @OA\Property(property="contact_person", type="string", example="John Doe"),
     *                 @OA\Property(property="address", type="string", example="Westlands Mall, Ground Floor, Shop G12"),
     *                 @OA\Property(property="city", type="string", example="Nairobi"),
     *                 @OA\Property(property="county", type="string", example="Nairobi"),
     *                 @OA\Property(
     *                     property="operating_hours",
     *                     type="object",
     *                     example={
     *                         "monday": {"open": "08:00", "close": "20:00"},
     *                         "tuesday": {"open": "08:00", "close": "20:00"},
     *                         "wednesday": {"open": "08:00", "close": "20:00"},
     *                         "thursday": {"open": "08:00", "close": "20:00"},
     *                         "friday": {"open": "08:00", "close": "20:00"},
     *                         "saturday": {"open": "09:00", "close": "18:00"},
     *                         "sunday": {"open": "10:00", "close": "16:00"}
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="delivery_info",
     *                     type="object",
     *                     example={
     *                         "available": true,
     *                         "zones_enabled": false
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="settings",
     *                     type="object",
     *                     example={
     *                         "currency": "KES",
     *                         "payment_methods": {"cash", "mpesa", "card", "bank_transfer"}
     *                     }
     *                 ),
     *                 @OA\Property(
     *                     property="social_media",
     *                     type="object",
     *                     example={
     *                         "facebook": "https://facebook.com/techhaven",
     *                         "instagram": "@techhaven_ke",
     *                         "twitter": "@TechHavenKE",
     *                         "whatsapp": "+254712345678"
     *                     }
     *                 ),
     *                 @OA\Property(property="rating", type="number", format="float", example=0, description="Average rating (0-5)"),
     *                 @OA\Property(property="rating_count", type="integer", example=0, description="Total number of ratings"),
     *                 @OA\Property(property="is_verified", type="boolean", example=false, description="Whether business is verified"),
     *                 @OA\Property(property="verified_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="status", type="string", enum={"pending", "approved", "rejected"}, example="pending", description="Approval status"),
     *                 @OA\Property(property="onboarded_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-30T14:36:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-30T14:36:22.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-11-30T14:36:22.921904Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="34ef50b6-abc8-4235-9f38-0a359d5cbb76"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Business details already submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Business details already submitted. Please contact admin for updates.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid or missing required fields",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "business_name": {"The business name field is required."},
     *                     "business_logo": {"The business logo must be an image.", "The business logo must not exceed 2MB."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function submit(SubmitBusinessDetailsRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->submitBusinessDetails(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::created(
            'Business details submitted successfully. Awaiting admin approval.',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/business-details",
     *     summary="Get business details",
     *     description="Get current tenant's business details submission",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Business details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business details retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/BusinessDetailResource")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Business details not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->getBusinessDetails(tenant()->id);

        if (!$businessDetail) {
            return ApiResponse::notFound('Business details not yet submitted');
        }

        return ApiResponse::success(
            'Business details retrieved successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/profile",
     *     summary="Update business profile",
     *     description="Update business name, description, email, phone, and contact person",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             required={"business_name", "business_phone"},
     *             @OA\Property(property="business_name", type="string", example="Tech Haven Electronics"),
     *             @OA\Property(property="business_description", type="string", example="Leading electronics retailer"),
     *             @OA\Property(property="business_email", type="string", format="email", example="info@techhaven.com"),
     *             @OA\Property(property="business_phone", type="string", example="+254712345678"),
     *             @OA\Property(property="contact_person", type="string", example="John Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business profile updated successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateProfile(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Business profile updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/business-details/media",
     *     summary="Update business media",
     *     description="Update business logo and banner images",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="business_logo", type="string", format="binary", description="Logo image (max 2MB)"),
     *                 @OA\Property(property="business_banner", type="string", format="binary", description="Banner image (max 5MB)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business media updated successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateMedia(UpdateMediaRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateMedia(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Business media updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/location",
     *     summary="Update business location",
     *     description="Update business address, city, and county",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             required={"address", "city", "county"},
     *             @OA\Property(property="address", type="string", example="123 Kimathi Street, Nairobi CBD"),
     *             @OA\Property(property="city", type="string", example="Nairobi"),
     *             @OA\Property(property="county", type="string", example="Nairobi")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business location updated successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateLocation(UpdateLocationRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateLocation(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Business location updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/operating-hours",
     *     summary="Update operating hours",
     *     description="Update business operating hours for specific days. Only provided days will be updated, other days remain unchanged. You can update all days at once or just specific days.",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="operating_hours",
     *                 type="object",
     *                 description="Operating hours object containing day(s) to update",
     *                 example={
     *                     "monday": {"open": "08:00", "close": "20:00"},
     *                     "friday": {"open": "08:00", "close": "18:00"},
     *                     "sunday": {"closed": true}
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_all_days",
     *                 type="object",
     *                 description="Example: Update all days at once",
     *                 example={
     *                     "operating_hours": {
     *                         "monday": {"open": "08:00", "close": "20:00"},
     *                         "tuesday": {"open": "08:00", "close": "20:00"},
     *                         "wednesday": {"open": "08:00", "close": "20:00"},
     *                         "thursday": {"open": "08:00", "close": "20:00"},
     *                         "friday": {"open": "08:00", "close": "20:00"},
     *                         "saturday": {"open": "09:00", "close": "18:00"},
     *                         "sunday": {"closed": true}
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_partial",
     *                 type="object",
     *                 description="Example: Update only specific days",
     *                 example={
     *                     "operating_hours": {
     *                         "saturday": {"open": "10:00", "close": "16:00"},
     *                         "sunday": {"open": "10:00", "close": "14:00"}
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Operating hours updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Operating hours updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated business details"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "operating_hours.monday.open": {"Opening time must be in HH:MM format (e.g., 08:00)."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateOperatingHours(UpdateOperatingHoursRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateOperatingHours(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Operating hours updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/delivery-info",
     *     summary="Update delivery information",
     *     description="Update delivery availability. Use `available` to enable or disable delivery for the business. Use `zones_enabled` to switch between zone-based fee calculation and free delivery (default until zones are configured). Fees and areas are managed through delivery zones.",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="delivery_info",
     *                 type="object",
     *                 description="Delivery configuration",
     *                 @OA\Property(property="available", type="boolean", example=true, description="Whether this merchant offers delivery"),
     *                 @OA\Property(property="zones_enabled", type="boolean", example=false, description="Whether zone-based fee calculation is active. Manage zones via the delivery-zones endpoints.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Delivery information updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Delivery information updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated business details"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "delivery_info.available": {"Delivery availability must be true or false."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateDeliveryInfo(UpdateDeliveryInfoRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateDeliveryInfo(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Delivery information updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/settings",
     *     summary="Update business settings",
     *     description="Update business settings. Only provided fields will be updated, other fields remain unchanged. You can update all fields at once or just specific fields.",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="settings",
     *                 type="object",
     *                 description="Settings object containing field(s) to update"
     *             ),
     *             @OA\Property(
     *                 property="example_all_fields",
     *                 type="object",
     *                 description="Example: Update all fields",
     *                 example={
     *                     "settings": {
     *                         "currency": "KES",
     *                         "tax_rate": 16,
     *                         "enable_online_store": true,
     *                         "enable_marketplace": true,
     *                         "payment_methods": {"cash", "mpesa", "card"}
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_partial",
     *                 type="object",
     *                 description="Example: Update only specific fields",
     *                 example={
     *                     "settings": {
     *                         "tax_rate": 18,
     *                         "payment_methods": {"cash", "mpesa", "card", "bank_transfer"}
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_single_field",
     *                 type="object",
     *                 description="Example: Update a single field",
     *                 example={
     *                     "settings": {
     *                         "enable_online_store": false
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business settings updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Business settings updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated business details"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "settings.currency": {"Currency must be a 3-letter code (e.g., KES, USD)."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateSettings(UpdateSettingsRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateSettings(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Business settings updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/business-details/social-media",
     *     summary="Update social media links",
     *     description="Update social media links. Only provided fields will be updated, other fields remain unchanged. You can update all links at once or just specific links.",
     *     tags={"Tenant Business Details"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="social_media",
     *                 type="object",
     *                 description="Social media object containing link(s) to update"
     *             ),
     *             @OA\Property(
     *                 property="example_all_fields",
     *                 type="object",
     *                 description="Example: Update all social media links",
     *                 example={
     *                     "social_media": {
     *                         "facebook": "https://facebook.com/techhaven",
     *                         "instagram": "@techhaven_ke",
     *                         "twitter": "@TechHavenKE",
     *                         "whatsapp": "+254712345678"
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_partial",
     *                 type="object",
     *                 description="Example: Update only specific links",
     *                 example={
     *                     "social_media": {
     *                         "instagram": "@techhaven_official",
     *                         "twitter": "@TechHaven_KE"
     *                     }
     *                 }
     *             ),
     *             @OA\Property(
     *                 property="example_single_link",
     *                 type="object",
     *                 description="Example: Update a single link",
     *                 example={
     *                     "social_media": {
     *                         "whatsapp": "+254700000000"
     *                     }
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Social media links updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Social media links updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Updated business details"
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 example={
     *                     "social_media.facebook": {"Facebook must be a valid URL."}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function updateSocialMedia(UpdateSocialMediaRequest $request): JsonResponse
    {
        $businessDetail = $this->businessDetailsService->updateSocialMedia(
            tenant()->id,
            $request->validated()
        );

        return ApiResponse::success(
            'Social media links updated successfully',
            new BusinessDetailResource($businessDetail)
        );
    }
}
