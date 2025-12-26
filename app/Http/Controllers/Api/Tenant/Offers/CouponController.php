<?php

namespace App\Http\Controllers\Api\Tenant\Offers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Offers\AttachBrandsRequest;
use App\Http\Requests\Tenant\Offers\AttachCategoriesRequest;
use App\Http\Requests\Tenant\Offers\AttachProductsRequest;
use App\Http\Requests\Tenant\Offers\BulkAttachProductsRequest;
use App\Http\Requests\Tenant\Offers\BulkDetachProductsRequest;
use App\Http\Requests\Tenant\Offers\CreateCouponRequest;
use App\Http\Requests\Tenant\Offers\UpdateCouponRequest;
use App\Http\Resources\Tenant\Offers\CouponDetailResource;
use App\Http\Resources\Tenant\Offers\CouponListResource;
use App\Http\Resources\Tenant\Offers\CouponResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Offers\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function __construct(
        protected CouponService $couponService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/coupons",
     *     summary="List all coupons",
     *     description="Retrieve a paginated list of coupons with optional filtering and sorting",
     *     operationId="listCoupons",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by coupon code or description",
     *         required=false,
     *         @OA\Schema(type="string", example="SAVE20")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by coupon status",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"active", "inactive", "expired", "upcoming"},
     *             example="active"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="applicable_to",
     *         in="query",
     *         description="Filter by applicability type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"all_products", "specific_products", "specific_categories", "specific_brands"},
     *             example="specific_products"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"created_at", "code", "valid_from", "valid_until", "usage_count"},
     *             default="created_at"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             default="desc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, minimum=1, maximum=100)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupons retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupons retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2),
     *                         @OA\Property(property="code", type="string", example="SAVE10"),
     *                         @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                         @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed_amount"}, example="percentage"),
     *                         @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                         @OA\Property(property="discount_value", type="string", example="20.00"),
     *                         @OA\Property(property="usage_count", type="integer", example=0),
     *                         @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                         @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                         @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                         @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                         @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                         @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="status", type="string", example="Upcoming", description="Computed status: Active, Inactive, Expired, Upcoming"),
     *                         @OA\Property(property="products_count", type="integer", example=0),
     *                         @OA\Property(property="categories_count", type="integer", example=0),
     *                         @OA\Property(property="brands_count", type="integer", example=3),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=2),
     *                     @OA\Property(property="from", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="to", type="integer", example=2, nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:03:47.558822Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="7c137705-efab-46af-bca6-76970f53cbf4"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search',
            'status',
            'applicable_to',
            'is_active',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->integer('per_page', 15);

        $coupons = $this->couponService->getPaginatedCoupons($filters, $perPage);

        return ApiResponse::paginated(
            CouponListResource::collection($coupons),
            'Coupons retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/coupons",
     *     summary="Create a new coupon",
     *     description="Create a new discount coupon with applicability rules for products, categories, or brands",
     *     operationId="createCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Coupon details",
     *         @OA\JsonContent(
     *             required={"code", "description", "discount_type", "discount_value", "valid_from", "valid_until", "applicable_to"},
     *             type="object",
     *             @OA\Property(
     *                 property="code",
     *                 type="string",
     *                 example="SAVE20",
     *                 description="Unique coupon code (uppercase letters, numbers, hyphens, underscores only)",
     *                 pattern="^[A-Z0-9_-]+$"
     *             ),
     *             @OA\Property(property="description", type="string", example="Get 20% off on all electronics", description="Coupon description"),
     *             @OA\Property(
     *                 property="discount_type",
     *                 type="string",
     *                 enum={"percentage", "fixed_amount"},
     *                 example="percentage",
     *                 description="Type of discount"
     *             ),
     *             @OA\Property(property="discount_value", type="number", format="float", example=20.00, description="Discount value (percentage or fixed amount)"),
     *             @OA\Property(property="min_purchase_amount", type="number", format="float", example=1000.00, nullable=true, description="Minimum purchase amount required to use coupon"),
     *             @OA\Property(property="max_discount_amount", type="number", format="float", example=500.00, nullable=true, description="Maximum discount amount (for percentage discounts)"),
     *             @OA\Property(property="usage_limit", type="integer", example=100, nullable=true, description="Total number of times coupon can be used"),
     *             @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true, description="Maximum uses per customer"),
     *             @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31", description="Coupon valid from date"),
     *             @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31", description="Coupon valid until date"),
     *             @OA\Property(
     *                 property="applicable_to",
     *                 type="string",
     *                 enum={"all_products", "specific_products", "specific_categories", "specific_brands"},
     *                 example="specific_categories",
     *                 description="What the coupon applies to"
     *             ),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true, description="Whether coupon is active"),
     *             @OA\Property(
     *                 property="applicability",
     *                 type="object",
     *                 nullable=true,
     *                 description="Applicability rules based on applicable_to type",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="product_id", type="integer", example=5),
     *                         @OA\Property(property="product_variant_id", type="integer", nullable=true, example=12)
     *                     ),
     *                     description="Products to attach (for specific_products type)"
     *                 ),
     *                 @OA\Property(
     *                     property="categories",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 11, 12},
     *                     description="Category IDs to attach (for specific_categories type)"
     *                 ),
     *                 @OA\Property(
     *                     property="brands",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={3},
     *                     description="Brand IDs to attach (for specific_brands type)"
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="Coupon created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Get 20% off on all electronics"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T14:57:17.964995Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="cbe9d0ac-87ac-4737-8800-d2dd6960b77c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="valid_until",
     *                     type="array",
     *                     @OA\Items(type="string", example="The valid until must be a date after valid from.")
     *                 ),
     *                 @OA\Property(
     *                     property="applicability.categories",
     *                     type="array",
     *                     @OA\Items(type="string", example="Categories are required when applicable_to is specific_categories.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function store(CreateCouponRequest $request): JsonResponse
    {
        $coupon = $this->couponService->createCoupon($request->validated());

        return ApiResponse::created(
            'Coupon created successfully',
            new CouponDetailResource($coupon)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/coupons/{id}",
     *     summary="Get coupon details",
     *     description="Retrieve detailed information about a specific coupon including applicability rules",
     *     operationId="getCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupon retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Get 20% off on all electronics"),
     *                 @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed_amount"}, example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false, description="Is currently valid (within date range and active)"),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false, description="Has reached usage limit"),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false, description="Can currently be used by customers"),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true, description="Can be edited (not used yet)"),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=5),
     *                             @OA\Property(property="name", type="string", example="iPhone 13"),
     *                             @OA\Property(property="sku", type="string", example="IP13-128GB")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="counts",
     *                     type="object",
     *                     @OA\Property(property="products", type="integer", example=0),
     *                     @OA\Property(property="categories", type="integer", example=3),
     *                     @OA\Property(property="brands", type="integer", example=0)
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:06:20.686778Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="14b24f2d-d1cb-46ba-b9e8-c344bd55a082"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function show(int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        return ApiResponse::success(
            'Coupon retrieved successfully',
            new CouponDetailResource($coupon)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/coupons/{id}",
     *     summary="Update coupon",
     *     description="Update coupon information. All fields are optional - only provide fields that need updating. Note: Some fields cannot be updated if coupon has been used.",
     *     operationId="updateCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=false,
     *         description="Coupon fields to update (all optional)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="code", type="string", example="NEWSAVE20", description="Coupon code"),
     *             @OA\Property(property="description", type="string", example="Updated description", description="Coupon description"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=25.00, description="Discount value"),
     *             @OA\Property(property="min_purchase_amount", type="number", format="float", example=1500.00, nullable=true, description="Minimum purchase amount"),
     *             @OA\Property(property="max_discount_amount", type="number", format="float", example=600.00, nullable=true, description="Maximum discount amount"),
     *             @OA\Property(property="usage_limit", type="integer", example=150, nullable=true, description="Total usage limit"),
     *             @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true, description="Usage limit per customer"),
     *             @OA\Property(property="valid_from", type="string", format="date", example="2025-01-01", description="Valid from date"),
     *             @OA\Property(property="valid_until", type="string", format="date", example="2025-12-31", description="Valid until date"),
     *             @OA\Property(property="is_active", type="boolean", example=false, description="Active status")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupon updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="25.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Inactive"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:15:54.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:15:54.055523Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="13319bd2-f564-467c-9799-9f5f1d389225"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function update(UpdateCouponRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->updateCoupon($coupon, $request->validated());

        return ApiResponse::success(
            'Coupon updated successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/coupons/{id}",
     *     summary="Delete coupon",
     *     description="Soft delete a coupon from the system. The coupon can be restored later if needed.",
     *     operationId="deleteCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupon deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:18:32.912182Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c42a1f75-bd19-4081-be9b-df9e4e94fd37"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $this->couponService->deleteCoupon($coupon);

        return ApiResponse::success('Coupon deleted successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/coupons/{id}/activate",
     *     summary="Activate coupon",
     *     description="Activate a coupon to make it available for use by customers",
     *     operationId="activateCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupon activated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon activated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="25.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:23:46.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:23:46.656188Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="62ac8d35-e4ea-47f2-a6cf-8ad0b4df3590"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function activate(int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $activatedCoupon = $this->couponService->activateCoupon($coupon);

        return ApiResponse::success(
            'Coupon activated successfully',
            new CouponResource($activatedCoupon)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/coupons/{id}/deactivate",
     *     summary="Deactivate coupon",
     *     description="Deactivate a coupon to prevent it from being used by customers",
     *     operationId="deactivateCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Coupon deactivated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Coupon deactivated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="25.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=false),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Inactive"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:24:57.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:24:57.199498Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9c292b97-b94f-43f7-8e5d-03568b9c6690"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function deactivate(int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $deactivatedCoupon = $this->couponService->deactivateCoupon($coupon);

        return ApiResponse::success(
            'Coupon deactivated successfully',
            new CouponResource($deactivatedCoupon)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/coupons/{id}/categories",
     *     summary="Attach categories to coupon",
     *     description="Attach multiple categories to a coupon. Only works for coupons with applicable_to set to 'specific_categories'.",
     *     operationId="attachCategoriesToCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Category IDs to attach",
     *         @OA\JsonContent(
     *             required={"category_ids"},
     *             type="object",
     *             @OA\Property(
     *                 property="category_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={14, 15, 13},
     *                 description="Array of category IDs to attach to the coupon",
     *                 minItems=1
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Categories attached to coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categories attached to coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="25.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:25:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:32:45.192621Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="c2bbd058-d2cd-4dda-8406-9b9e9924bf42"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="category_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The category ids field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_to",
     *                     type="array",
     *                     @OA\Items(type="string", example="Can only attach categories to coupons with applicable_to set to specific_categories.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function attachCategories(AttachCategoriesRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->attachCategories($coupon, $request->validated()['category_ids']);

        return ApiResponse::success(
            'Categories attached to coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/coupons/{id}/categories/{categoryId}",
     *     summary="Detach category from coupon",
     *     description="Remove a specific category from a coupon's applicability rules",
     *     operationId="detachCategoryFromCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         description="Category ID to detach",
     *         required=true,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Category detached from coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category detached from coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="code", type="string", example="SAVE20"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="25.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=100, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2025-12-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_categories"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Electronics"),
     *                             @OA\Property(property="slug", type="string", example="electronics")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T14:57:17.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:25:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:37:05.234105Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a5551eb0-e8d4-4ce6-9136-e2d71cc6278c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon or category not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function detachCategory(int $id, int $categoryId): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->detachCategory($coupon, $categoryId);

        return ApiResponse::success(
            'Category detached from coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/coupons/{id}/brands",
     *     summary="Attach brands to coupon",
     *     description="Attach multiple brands to a coupon. Only works for coupons with applicable_to set to 'specific_brands'.",
     *     operationId="attachBrandsToCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Brand IDs to attach",
     *         @OA\JsonContent(
     *             required={"brand_ids"},
     *             type="object",
     *             @OA\Property(
     *                 property="brand_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={4, 5, 7},
     *                 description="Array of brand IDs to attach to the coupon",
     *                 minItems=1
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Brands attached to coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brands attached to coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:43:32.433837Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b3281fdb-1445-4b05-9acc-7f987eaa6100"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="brand_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The brand ids field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_to",
     *                     type="array",
     *                     @OA\Items(type="string", example="Can only attach brands to coupons with applicable_to set to specific_brands.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function attachBrands(AttachBrandsRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->attachBrands($coupon, $request->validated()['brand_ids']);

        return ApiResponse::success(
            'Brands attached to coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/coupons/{id}/brands/{brandId}",
     *     summary="Detach brand from coupon",
     *     description="Remove a specific brand from a coupon's applicability rules",
     *     operationId="detachBrandFromCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         description="Brand ID to detach",
     *         required=true,
     *         @OA\Schema(type="integer", example=11)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Brand detached from coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand detached from coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:46:18.992530Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="32fefb3d-4919-453f-a584-9f00562551c3"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon or brand not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function detachBrand(int $id, int $brandId): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->detachBrand($coupon, $brandId);

        return ApiResponse::success(
            'Brand detached from coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/coupons/{id}/products",
     *     summary="Attach products to coupon",
     *     description="Attach multiple products (with optional variants) to a coupon. Only works for coupons with applicable_to set to 'specific_products' or 'all_products'.",
     *     operationId="attachProductsToCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Products to attach (with optional variants)",
     *         @OA\JsonContent(
     *             required={"products"},
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 description="Array of products to attach to the coupon",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id"},
     *                     @OA\Property(property="product_id", type="integer", example=1, description="Product ID"),
     *                     @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null, description="Product variant ID (optional, null for base product)")
     *                 ),
     *                 example={
     *                     {"product_id": 1, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": 1}
     *                 }
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Products attached to coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products attached to coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Products"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="variant_name", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:55:00.503191Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="39721e70-36ab-445b-9359-eaa93256127d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="string", example="The products field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_to",
     *                     type="array",
     *                     @OA\Items(type="string", example="Can only attach products to coupons with applicable_to set to specific_products or all_products.")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:52:38.835625Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="704423bb-5b1e-4a7e-bd1c-c8dbabb69f9a"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function attachProducts(AttachProductsRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->attachProducts($coupon, $request->validated()['products']);

        return ApiResponse::success(
            'Products attached to coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/coupons/{id}/products/{productId}",
     *     summary="Detach product from coupon",
     *     description="Remove a specific product (including all its variants) from a coupon's applicability rules",
     *     operationId="detachProductFromCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID to detach",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Product detached from coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product detached from coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Products"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="variant_id", type="integer", example=2),
     *                             @OA\Property(property="variant_name", type="string", example="55C725-GAL")
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T15:58:32.695604Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9cb56c04-51a0-4793-bce7-ebc006ac067c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon or product not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function detachProduct(int $id, int $productId): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->detachProduct($coupon, $productId);

        return ApiResponse::success(
            'Product detached from coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/coupons/{id}/products/bulk",
     *     summary="Bulk attach products to coupon",
     *     description="Attach multiple products (with optional variants) to a coupon in a single request. Only works for coupons with applicable_to set to 'specific_products' or 'all_products'.",
     *     operationId="bulkAttachProductsToCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Products to attach in bulk (with optional variants)",
     *         @OA\JsonContent(
     *             required={"products"},
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 description="Array of products to attach to the coupon",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id"},
     *                     @OA\Property(property="product_id", type="integer", example=1, description="Product ID"),
     *                     @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null, description="Product variant ID (optional, null for base product)")
     *                 ),
     *                 example={
     *                     {"product_id": 1, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": 2}
     *                 }
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Products bulk attached to coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products bulk attached to coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Products"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="variant_name", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T18:18:05.439108Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="aefca03e-9619-4bc4-8f03-084e5ae9edd9"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="string", example="The products field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_to",
     *                     type="array",
     *                     @OA\Items(type="string", example="Can only attach products to coupons with applicable_to set to specific_products or all_products.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function bulkAttachProducts(BulkAttachProductsRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->bulkAttachProducts($coupon, $request->validated()['products']);

        return ApiResponse::success(
            'Products bulk attached to coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/coupons/{id}/products/bulk",
     *     summary="Bulk detach products from coupon",
     *     description="Remove multiple products (including all their variants) from a coupon's applicability rules in a single request",
     *     operationId="bulkDetachProductsFromCoupon",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Coupon ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product IDs to detach in bulk",
     *         @OA\JsonContent(
     *             required={"product_ids"},
     *             type="object",
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={2, 3},
     *                 description="Array of product IDs to detach from the coupon",
     *                 minItems=1
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Products bulk detached from coupon successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products bulk detached from coupon successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="code", type="string", example="SAVE10"),
     *                 @OA\Property(property="description", type="string", example="Get 10% off on all electronic brands"),
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="20.00"),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true),
     *                 @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true),
     *                 @OA\Property(property="usage_limit", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=50, nullable=true),
     *                 @OA\Property(property="usage_limit_per_customer", type="integer", example=2, nullable=true),
     *                 @OA\Property(property="valid_from", type="string", format="date", example="2026-01-31"),
     *                 @OA\Property(property="valid_until", type="string", format="date", example="2026-11-30"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Products"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Upcoming"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true, example=null),
     *                             @OA\Property(property="variant_name", type="string", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung"),
     *                             @OA\Property(property="slug", type="string", example="samsung")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-12-26T15:03:35.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-12-26T15:18:32.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T18:48:01.153088Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="30b508f4-91f8-4a5c-a3f7-b653f63e4c90"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Coupon not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The requested resource was not found."),
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
     *     
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="The product ids field is required.")
     *                 )
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
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function bulkDetachProducts(BulkDetachProductsRequest $request, int $id): JsonResponse
    {
        $coupon = $this->couponService->getCouponById($id);

        if (!$coupon) {
            return ApiResponse::notFound('Coupon not found');
        }

        $updatedCoupon = $this->couponService->bulkDetachProducts($coupon, $request->validated()['product_ids']);

        return ApiResponse::success(
            'Products bulk detached from coupon successfully',
            new CouponDetailResource($updatedCoupon)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/coupons/available-coupons",
     *     summary="Get available coupons",
     *     description="Retrieve all coupons that are currently valid, active, and available for use by customers. Returns only coupons where is_valid=true, is_active=true, not expired, and not exhausted.",
     *     operationId="getAvailableCoupons",
     *     tags={"Coupons"},
     *     security={{"sanctum": {}}},
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Available coupons retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available coupons retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="Array of available coupons (not paginated)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="code", type="string", example="SAVE20"),
     *                     @OA\Property(property="description", type="string", example="Updated description"),
     *                     @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed_amount"}, example="percentage"),
     *                     @OA\Property(property="discount_type_label", type="string", example="Percentage Discount"),
     *                     @OA\Property(property="discount_value", type="string", example="25.00"),
     *                     @OA\Property(property="min_purchase_amount", type="string", example="1000.00", nullable=true, description="Minimum purchase required to use this coupon"),
     *                     @OA\Property(property="max_discount_amount", type="string", example="500.00", nullable=true, description="Maximum discount amount (for percentage discounts)"),
     *                     @OA\Property(property="usage_limit", type="integer", example=100, nullable=true, description="Total usage limit across all customers"),
     *                     @OA\Property(property="usage_count", type="integer", example=20, description="Number of times coupon has been used"),
     *                     @OA\Property(property="remaining_usage", type="integer", example=80, nullable=true, description="Remaining uses available"),
     *                     @OA\Property(property="usage_limit_per_customer", type="integer", example=1, nullable=true, description="Maximum uses per customer"),
     *                     @OA\Property(property="valid_from", type="string", format="date", example="2025-12-25", description="Coupon valid from date"),
     *                     @OA\Property(property="valid_until", type="string", format="date", example="2026-12-31", description="Coupon valid until date"),
     *                     @OA\Property(property="applicable_to", type="string", enum={"all_products", "specific_products", "specific_categories", "specific_brands"}, example="specific_categories"),
     *                     @OA\Property(property="applicable_to_label", type="string", example="Specific Categories"),
     *                     @OA\Property(property="is_active", type="boolean", example=true, description="Always true for available coupons"),
     *                     @OA\Property(property="is_expired", type="boolean", example=false, description="Always false for available coupons"),
     *                     @OA\Property(property="is_valid", type="boolean", example=true, description="Always true for available coupons (within valid date range)"),
     *                     @OA\Property(property="is_exhausted", type="boolean", example=false, description="Always false for available coupons (not reached usage limit)"),
     *                     @OA\Property(property="status", type="string", example="Active", description="Current status - will always be 'Active' for available coupons"),
     *                     @OA\Property(property="can_be_used", type="boolean", example=true, description="Always true for available coupons"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=false, description="Whether coupon can still be edited (false if already used)"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", nullable=true, example=null),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true, example=null)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-26T20:58:51.341047Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="678f0dab-a49f-4a16-92f9-3d2eb3f1d2b8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=201,
     *         description="No available coupons (empty array)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Available coupons retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(type="object"),
     *                 example={}
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
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An unexpected error occurred."),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time"),
     *                 @OA\Property(property="request_id", type="string", format="uuid"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", nullable=true),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true)
     *             )
     *         )
     *     )
     * )
     */
    public function availableCoupons(): JsonResponse
    {
        $coupons = $this->couponService->getAvailableCoupons();

        return ApiResponse::success(
            'Available coupons retrieved successfully',
            CouponResource::collection($coupons)
        );
    }
}
