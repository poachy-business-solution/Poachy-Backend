<?php

namespace App\Http\Controllers\Api\Tenant\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Business\SubmitBusinessDetailsRequest;
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
     *                     description="Delivery service information",
     *                     example={
     *                         "available": true,
     *                         "areas": {"Nairobi", "Kiambu", "Machakos", "Kajiado"},
     *                         "fee": 200,
     *                         "free_delivery_threshold": 5000
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
     *                         "available": "1",
     *                         "areas": {"Nairobi", "Kiambu", "Machakos", "Kajiado"},
     *                         "fee": "200",
     *                         "free_delivery_threshold": "5000"
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
}
