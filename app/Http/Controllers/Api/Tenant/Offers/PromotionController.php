<?php

namespace App\Http\Controllers\Api\Tenant\Offers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Offers\AttachBrandsRequest;
use App\Http\Requests\Tenant\Offers\AttachCategoriesRequest;
use App\Http\Requests\Tenant\Offers\AttachProductsRequest;
use App\Http\Requests\Tenant\Offers\BulkAttachProductsRequest;
use App\Http\Requests\Tenant\Offers\BulkDetachProductsRequest;
use App\Http\Requests\Tenant\Offers\CreatePromotionRequest;
use App\Http\Requests\Tenant\Offers\UpdateCustomerGroupsRequest;
use App\Http\Requests\Tenant\Offers\UpdatePromotionBannerRequest;
use App\Http\Requests\Tenant\Offers\UpdatePromotionRequest;
use App\Http\Requests\Tenant\Offers\UpdateStoresRequest;
use App\Http\Resources\Tenant\Offers\ActivePromotionResource;
use App\Http\Resources\Tenant\Offers\PromotionDetailResource;
use App\Http\Resources\Tenant\Offers\PromotionListResource;
use App\Http\Resources\Tenant\Offers\PromotionResource;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Offers\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        protected PromotionService $promotionService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions",
     *     tags={"Tenant - Promotions"},
     *     summary="List all promotions",
     *     description="Retrieves a paginated list of promotions for the tenant with filtering and sorting capabilities. Returns promotion summary information including status, usage counts, and applicability details.",
     *     operationId="listPromotions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term to filter promotions by name, code, or description",
     *         required=false,
     *         @OA\Schema(type="string", example="black")
     *     ),
     *     @OA\Parameter(
     *         name="promotion_type",
     *         in="query",
     *         description="Filter by promotion type",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"percentage_discount", "fixed_discount", "buy_x_get_y"},
     *             example="percentage_discount"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="applicable_to",
     *         in="query",
     *         description="Filter by applicability scope",
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
     *         name="store_id",
     *         in="query",
     *         description="Filter promotions applicable to specific store",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Parameter(
     *         name="show_in_pos",
     *         in="query",
     *         description="Filter promotions that should be shown in POS",
     *         required=false,
     *         @OA\Schema(type="boolean", example=true)
     *     ),
     *     @OA\Parameter(
     *         name="show_on_website",
     *         in="query",
     *         description="Filter promotions that should be shown on website",
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
     *             enum={"name", "code", "start_date", "end_date", "display_priority", "created_at"},
     *             example="display_priority"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"},
     *             example="desc"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15, example=20)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1, example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=6),
     *                         @OA\Property(property="name", type="string", example="VIP Exclusive - 25% Off"),
     *                         @OA\Property(property="code", type="string", example="VIP25"),
     *                         @OA\Property(property="description", type="string", nullable=true, example="Exclusive discount for VIP members"),
     *                         @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                         @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                         @OA\Property(property="discount_value", type="string", nullable=true, example="25.00"),
     *                         @OA\Property(property="total_usage_count", type="integer", example=0),
     *                         @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="start_date", type="string", format="date-time", example="2026-02-01 00:00:00"),
     *                         @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                         @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                         @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                         @OA\Property(property="show_on_website", type="boolean", example=true),
     *                         @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                         @OA\Property(property="display_priority", type="integer", example=7),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="auto_apply", type="boolean", example=true),
     *                         @OA\Property(property="status", type="string", example="Scheduled", description="Human-readable status: Active, Scheduled, Expired, Exhausted, Inactive"),
     *                         @OA\Property(property="products_count", type="integer", example=0, description="Number of products this promotion applies to"),
     *                         @OA\Property(property="categories_count", type="integer", example=0, description="Number of categories this promotion applies to"),
     *                         @OA\Property(property="brands_count", type="integer", example=0, description="Number of brands this promotion applies to"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:12:31.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="pagination",
     *                     type="object",
     *                     @OA\Property(property="current_page", type="integer", example=1),
     *                     @OA\Property(property="last_page", type="integer", example=1),
     *                     @OA\Property(property="per_page", type="integer", example=15),
     *                     @OA\Property(property="total", type="integer", example=6),
     *                     @OA\Property(property="from", type="integer", example=1),
     *                     @OA\Property(property="to", type="integer", example=6)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:18:13.604267Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="06692cc2-fb7a-407a-b2df-6cdc1bbc4845"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
            'promotion_type',
            'applicable_to',
            'is_active',
            'store_id',
            'show_in_pos',
            'show_on_website',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->integer('per_page', 15);

        $promotions = $this->promotionService->getPaginatedPromotions($filters, $perPage);

        return ApiResponse::paginated(
            PromotionListResource::collection($promotions),
            'Promotions retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions",
     *     tags={"Tenant - Promotions"},
     *     summary="Create a new promotion",
     *     description="Creates a new promotion for the tenant. Supports multiple promotion types: percentage discount, fixed discount, buy X get Y, and can be scoped to specific products, categories, brands, stores, or customer groups.",
     *     operationId="createPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Promotion creation data",
     *         @OA\JsonContent(
     *             required={"name", "code", "promotion_type", "start_date", "end_date", "applicable_to"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 description="Promotion name",
     *                 example="Black Friday Sale"
     *             ),
     *             @OA\Property(
     *                 property="code",
     *                 type="string",
     *                 maxLength=50,
     *                 pattern="^[A-Z0-9-_]+$",
     *                 description="Unique promotion code (uppercase alphanumeric, hyphens, underscores only)",
     *                 example="BLACKFRIDAY2025"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 maxLength=1000,
     *                 nullable=true,
     *                 description="Promotion description",
     *                 example="Massive discounts on all products for Black Friday!"
     *             ),
     *             @OA\Property(
     *                 property="promotion_type",
     *                 type="string",
     *                 enum={"percentage_discount", "fixed_discount", "buy_x_get_y"},
     *                 description="Type of promotion",
     *                 example="percentage_discount"
     *             ),
     *             @OA\Property(
     *                 property="discount_value",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0.01,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Discount value (percentage or fixed amount). Required for percentage_discount and fixed_discount types",
     *                 example=30.00
     *             ),
     *             @OA\Property(
     *                 property="buy_quantity",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Number of items to buy. Required for buy_x_get_y promotion type",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="get_quantity",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Number of items to get. Required for buy_x_get_y promotion type",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="get_items_free",
     *                 type="boolean",
     *                 description="Whether get items are free (true) or discounted (false). Defaults to true",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="get_items_discount_percentage",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=100,
     *                 nullable=true,
     *                 description="Discount percentage for get items if not free",
     *                 example=50.00
     *             ),
     *             @OA\Property(
     *                 property="min_purchase_amount",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Minimum purchase amount required to use promotion",
     *                 example=1000.00
     *             ),
     *             @OA\Property(
     *                 property="max_discount_amount",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Maximum discount amount (cap for percentage discounts)",
     *                 example=5000.00
     *             ),
     *             @OA\Property(
     *                 property="max_uses_per_customer",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Maximum times a customer can use this promotion",
     *                 example=5
     *             ),
     *             @OA\Property(
     *                 property="total_usage_limit",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Total number of times promotion can be used across all customers",
     *                 example=1000
     *             ),
     *             @OA\Property(
     *                 property="start_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Promotion start date and time (must be today or future)",
     *                 example="2026-01-25 00:00:00"
     *             ),
     *             @OA\Property(
     *                 property="end_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Promotion end date and time (must be after start_date)",
     *                 example="2026-11-29 23:59:59"
     *             ),
     *             @OA\Property(
     *                 property="active_days",
     *                 type="array",
     *                 nullable=true,
     *                 description="Days of week when promotion is active",
     *                 @OA\Items(
     *                     type="string",
     *                     enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}
     *                 ),
     *                 example={"friday", "saturday", "sunday"}
     *             ),
     *             @OA\Property(
     *                 property="active_time_start",
     *                 type="string",
     *                 format="time",
     *                 pattern="^([0-1][0-9]|2[0-3]):[0-5][0-9]$",
     *                 nullable=true,
     *                 description="Daily start time for promotion (HH:MM format)",
     *                 example="18:00"
     *             ),
     *             @OA\Property(
     *                 property="active_time_end",
     *                 type="string",
     *                 format="time",
     *                 pattern="^([0-1][0-9]|2[0-3]):[0-5][0-9]$",
     *                 nullable=true,
     *                 description="Daily end time for promotion (HH:MM format, must be after active_time_start)",
     *                 example="22:00"
     *             ),
     *             @OA\Property(
     *                 property="applicable_store_ids",
     *                 type="array",
     *                 nullable=true,
     *                 description="Store IDs where promotion is applicable. Null means all stores",
     *                 @OA\Items(type="integer"),
     *                 example={1, 3, 5}
     *             ),
     *             @OA\Property(
     *                 property="applicable_customer_group_ids",
     *                 type="array",
     *                 nullable=true,
     *                 description="Customer group IDs eligible for promotion. Null means all customers",
     *                 @OA\Items(type="integer"),
     *                 example={2, 4}
     *             ),
     *             @OA\Property(
     *                 property="applicable_to",
     *                 type="string",
     *                 enum={"all_products", "specific_products", "specific_categories", "specific_brands"},
     *                 description="Scope of promotion applicability",
     *                 example="all_products"
     *             ),
     *             @OA\Property(
     *                 property="show_on_website",
     *                 type="boolean",
     *                 description="Whether to display promotion on website. Defaults to true",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="show_in_pos",
     *                 type="boolean",
     *                 description="Whether to display promotion in POS. Defaults to true",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="banner_image_url",
     *                 type="string",
     *                 nullable=true,
     *                 description="URL to promotion banner image",
     *                 example="https://example.com/banners/blackfriday.jpg"
     *             ),
     *             @OA\Property(
     *                 property="display_priority",
     *                 type="integer",
     *                 minimum=0,
     *                 maximum=999,
     *                 description="Display priority (higher number = higher priority). Defaults to 0",
     *                 example=10
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 description="Whether promotion is active. Defaults to true",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="auto_apply",
     *                 type="boolean",
     *                 description="Whether to automatically apply promotion at checkout. Defaults to true",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="applicability",
     *                 type="object",
     *                 nullable=true,
     *                 description="Detailed applicability configuration. Required when applicable_to is not 'all_products'",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     description="Product-specific applicability. Required when applicable_to is 'specific_products'",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"product_id"},
     *                         @OA\Property(
     *                             property="product_id",
     *                             type="integer",
     *                             description="Product ID",
     *                             example=15
     *                         ),
     *                         @OA\Property(
     *                             property="product_variant_id",
     *                             type="integer",
     *                             nullable=true,
     *                             description="Product variant ID (optional, null means all variants)",
     *                             example=23
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="categories",
     *                     type="array",
     *                     description="Category IDs. Required when applicable_to is 'specific_categories'",
     *                     @OA\Items(type="integer"),
     *                     example={5, 8, 12}
     *                 ),
     *                 @OA\Property(
     *                     property="brands",
     *                     type="array",
     *                     description="Brand IDs. Required when applicable_to is 'specific_brands'",
     *                     @OA\Items(type="integer"),
     *                     example={3, 7}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Promotion created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="30.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="5000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(
     *                     property="active_days",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="string"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=10),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false, description="Whether promotion end date has passed"),
     *                 @OA\Property(property="is_valid", type="boolean", example=false, description="Whether promotion is currently within valid date range"),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false, description="Whether promotion usage limit has been reached"),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false, description="Whether promotion is active right now (considering all conditions)"),
     *                 @OA\Property(property="status", type="string", example="Scheduled", description="Human-readable promotion status"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false, description="Whether promotion can currently be used"),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true, description="Whether promotion can be edited"),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(type="object"),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(type="object"),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(type="object"),
     *                         example={}
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="counts",
     *                     type="array",
     *                     @OA\Items(type="object"),
     *                     example={}
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:02:07.314546Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="08670b2d-c304-41d8-810d-738e248e5cb9"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="end_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The end date field must be a date after start date.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function store(CreatePromotionRequest $request): JsonResponse
    {
        $promotion = $this->promotionService->createPromotion($request->validated());

        return ApiResponse::created(
            'Promotion created successfully',
            new PromotionDetailResource($promotion)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions/{id}",
     *     tags={"Tenant - Promotions"},
     *     summary="Get promotion details",
     *     description="Retrieves detailed information about a specific promotion including all configuration, applicability rules, usage statistics, and status indicators. Returns comprehensive data about products, categories, and brands associated with the promotion.",
     *     operationId="showPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="30.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="5000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0, description="Current number of times promotion has been used"),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000, description="Number of remaining uses before limit is reached"),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(
     *                     property="active_days",
     *                     type="array",
     *                     nullable=true,
     *                     description="Days of week when promotion is active",
     *                     @OA\Items(type="string"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null, description="Human-readable active days (e.g., 'Friday, Saturday, Sunday')"),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null, description="Human-readable time window (e.g., '18:00 - 22:00')"),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=10),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false, description="Whether promotion end date has passed"),
     *                 @OA\Property(property="is_valid", type="boolean", example=false, description="Whether current date/time is within promotion validity period"),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false, description="Whether promotion usage limit has been reached"),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false, description="Whether promotion is active right now considering all conditions (date, time, day, status)"),
     *                 @OA\Property(property="status", type="string", example="Scheduled", description="Human-readable status: Active, Scheduled, Expired, Exhausted, Inactive"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false, description="Whether promotion can currently be applied to orders"),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true, description="Whether promotion can be edited (false if already used)"),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Detailed applicability configuration with actual product/category/brand data",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         description="Products this promotion applies to (empty for all_products)",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="product_id", type="integer", example=15),
     *                             @OA\Property(property="product_name", type="string", example="Samsung Galaxy S24"),
     *                             @OA\Property(property="product_sku", type="string", example="SAMS24-128GB"),
     *                             @OA\Property(property="product_variant_id", type="integer", nullable=true, example=23),
     *                             @OA\Property(property="variant_name", type="string", nullable=true, example="128GB Black")
     *                         ),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         description="Categories this promotion applies to",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="category_id", type="integer", example=5),
     *                             @OA\Property(property="category_name", type="string", example="Electronics"),
     *                             @OA\Property(property="category_slug", type="string", example="electronics")
     *                         ),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         description="Brands this promotion applies to",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="brand_id", type="integer", example=3),
     *                             @OA\Property(property="brand_name", type="string", example="Samsung"),
     *                             @OA\Property(property="brand_slug", type="string", example="samsung")
     *                         ),
     *                         example={}
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="counts",
     *                     type="object",
     *                     description="Count of products, categories, and brands",
     *                     @OA\Property(property="products", type="integer", example=0),
     *                     @OA\Property(property="categories", type="integer", example=0),
     *                     @OA\Property(property="brands", type="integer", example=0)
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:20:35.742147Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b809e605-6fcd-4528-90ae-28d1c830c057"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        return ApiResponse::success(
            'Promotion retrieved successfully',
            new PromotionDetailResource($promotion)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/promotions/{id}",
     *     tags={"Tenant - Promotions"},
     *     summary="Update a promotion",
     *     description="Updates an existing promotion. All fields are optional - only provided fields will be updated. Cannot update promotions that have already been used unless changing non-critical fields like display settings. Supports partial updates for all promotion types and applicability configurations.",
     *     operationId="updatePromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Promotion update data (all fields optional)",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 maxLength=255,
     *                 description="Promotion name",
     *                 example="Black Friday MEGA Sale"
     *             ),
     *             @OA\Property(
     *                 property="code",
     *                 type="string",
     *                 maxLength=50,
     *                 pattern="^[A-Z0-9-_]+$",
     *                 description="Unique promotion code (uppercase alphanumeric, hyphens, underscores only)",
     *                 example="BLACKFRIDAY2025"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 maxLength=1000,
     *                 nullable=true,
     *                 description="Promotion description",
     *                 example="Updated massive discounts on all products!"
     *             ),
     *             @OA\Property(
     *                 property="promotion_type",
     *                 type="string",
     *                 enum={"percentage_discount", "fixed_discount", "buy_x_get_y"},
     *                 description="Type of promotion",
     *                 example="percentage_discount"
     *             ),
     *             @OA\Property(
     *                 property="discount_value",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0.01,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Discount value (percentage or fixed amount)",
     *                 example=40.00
     *             ),
     *             @OA\Property(
     *                 property="buy_quantity",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Number of items to buy (for buy_x_get_y)",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="get_quantity",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Number of items to get (for buy_x_get_y)",
     *                 example=1
     *             ),
     *             @OA\Property(
     *                 property="get_items_free",
     *                 type="boolean",
     *                 description="Whether get items are free or discounted",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="get_items_discount_percentage",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=100,
     *                 nullable=true,
     *                 description="Discount percentage for get items if not free",
     *                 example=50.00
     *             ),
     *             @OA\Property(
     *                 property="min_purchase_amount",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Minimum purchase amount required",
     *                 example=1500.00
     *             ),
     *             @OA\Property(
     *                 property="max_discount_amount",
     *                 type="number",
     *                 format="decimal",
     *                 minimum=0,
     *                 maximum=999999.99,
     *                 nullable=true,
     *                 description="Maximum discount amount (cap)",
     *                 example=8000.00
     *             ),
     *             @OA\Property(
     *                 property="max_uses_per_customer",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Maximum uses per customer",
     *                 example=3
     *             ),
     *             @OA\Property(
     *                 property="total_usage_limit",
     *                 type="integer",
     *                 minimum=1,
     *                 nullable=true,
     *                 description="Total usage limit",
     *                 example=2000
     *             ),
     *             @OA\Property(
     *                 property="start_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Promotion start date and time",
     *                 example="2026-01-20 00:00:00"
     *             ),
     *             @OA\Property(
     *                 property="end_date",
     *                 type="string",
     *                 format="date-time",
     *                 description="Promotion end date and time (must be after start_date)",
     *                 example="2026-12-31 23:59:59"
     *             ),
     *             @OA\Property(
     *                 property="active_days",
     *                 type="array",
     *                 nullable=true,
     *                 description="Days of week when promotion is active",
     *                 @OA\Items(
     *                     type="string",
     *                     enum={"monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"}
     *                 ),
     *                 example={"friday", "saturday"}
     *             ),
     *             @OA\Property(
     *                 property="active_time_start",
     *                 type="string",
     *                 format="time",
     *                 pattern="^([0-1][0-9]|2[0-3]):[0-5][0-9]$",
     *                 nullable=true,
     *                 description="Daily start time (HH:MM)",
     *                 example="17:00"
     *             ),
     *             @OA\Property(
     *                 property="active_time_end",
     *                 type="string",
     *                 format="time",
     *                 pattern="^([0-1][0-9]|2[0-3]):[0-5][0-9]$",
     *                 nullable=true,
     *                 description="Daily end time (HH:MM, must be after active_time_start)",
     *                 example="23:00"
     *             ),
     *             @OA\Property(
     *                 property="applicable_store_ids",
     *                 type="array",
     *                 nullable=true,
     *                 description="Store IDs where promotion applies",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             ),
     *             @OA\Property(
     *                 property="applicable_customer_group_ids",
     *                 type="array",
     *                 nullable=true,
     *                 description="Customer group IDs eligible for promotion",
     *                 @OA\Items(type="integer"),
     *                 example={1, 3}
     *             ),
     *             @OA\Property(
     *                 property="applicable_to",
     *                 type="string",
     *                 enum={"all_products", "specific_products", "specific_categories", "specific_brands"},
     *                 description="Applicability scope",
     *                 example="all_products"
     *             ),
     *             @OA\Property(
     *                 property="show_on_website",
     *                 type="boolean",
     *                 description="Display on website",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="show_in_pos",
     *                 type="boolean",
     *                 description="Display in POS",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="banner_image_url",
     *                 type="string",
     *                 nullable=true,
     *                 description="Banner image URL",
     *                 example="https://example.com/banners/mega-sale.jpg"
     *             ),
     *             @OA\Property(
     *                 property="display_priority",
     *                 type="integer",
     *                 minimum=0,
     *                 maximum=999,
     *                 description="Display priority (higher = more prominent)",
     *                 example=30
     *             ),
     *             @OA\Property(
     *                 property="is_active",
     *                 type="boolean",
     *                 description="Active status",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="auto_apply",
     *                 type="boolean",
     *                 description="Auto-apply at checkout",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="applicability",
     *                 type="object",
     *                 nullable=true,
     *                 description="Detailed applicability configuration",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     description="Product-specific applicability",
     *                     @OA\Items(
     *                         type="object",
     *                         required={"product_id"},
     *                         @OA\Property(property="product_id", type="integer", example=15),
     *                         @OA\Property(property="product_variant_id", type="integer", nullable=true, example=23)
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="categories",
     *                     type="array",
     *                     description="Category IDs",
     *                     @OA\Items(type="integer"),
     *                     example={5, 8}
     *                 ),
     *                 @OA\Property(
     *                     property="brands",
     *                     type="array",
     *                     description="Brand IDs",
     *                     @OA\Items(type="integer"),
     *                     example={3}
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(
     *                     property="active_days",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="string"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     nullable=true,
     *                     @OA\Items(type="integer"),
     *                     example=null
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object"), example={}),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object"), example={}),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"), example={})
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:24:04.538135Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="0554ea09-b385-4890-8eab-bd3322f1cc00"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="code",
     *                     type="array",
     *                     @OA\Items(type="string", example="The code has already been taken.")
     *                 ),
     *                 @OA\Property(
     *                     property="end_date",
     *                     type="array",
     *                     @OA\Items(type="string", example="The end date field must be a date after start date.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function update(UpdatePromotionRequest $request, int $id): JsonResponse
    {
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->updatePromotion($promotion, $request->validated());

        return ApiResponse::success(
            'Promotion updated successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}",
     *     tags={"Tenant - Promotions"},
     *     summary="Delete a promotion",
     *     description="Soft deletes a promotion. The promotion will be marked as deleted but retained in the database for audit purposes. Deletion is only allowed for promotions that have not been used or have zero usage count. Once deleted, the promotion cannot be recovered and will not appear in any listings.",
     *     operationId="deletePromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID to delete",
     *         required=true,
     *         @OA\Schema(type="integer", example=6)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion deleted successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:26:51.800399Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="3d9bcff8-6752-4829-bc63-34d40c50f57b"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Cannot delete promotion that has been used",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot delete promotion that has already been used"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="usage_count", type="integer", example=45, description="Number of times the promotion has been used"),
     *                 @OA\Property(property="hint", type="string", example="Consider deactivating the promotion instead of deleting it")
     *             ),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $this->promotionService->deletePromotion($promotion);

        return ApiResponse::success('Promotion deleted successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/promotions/{id}/activate",
     *     tags={"Tenant - Promotions"},
     *     summary="Activate a promotion",
     *     description="Activates a previously deactivated promotion by setting is_active to true. Once activated, the promotion becomes available for use in POS and/or eCommerce systems (depending on show_in_pos and show_on_website settings) and will be included in active promotion queries. The promotion must be within its validity period (start_date to end_date) to be effectively usable after activation. Returns the complete updated promotion data with all status flags.",
     *     operationId="activatePromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID to activate",
     *         required=true,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion activated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion activated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                 @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-04 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="display_priority", type="integer", example=6),
     *                 @OA\Property(property="is_active", type="boolean", example=true, description="Now set to true after activation"),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Active", description="Status updated to Active if within validity period"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=true, description="True if promotion can now be applied"),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:48:25.000000Z", description="Timestamp reflects activation time")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:48:25.774232Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="23abb5cb-f973-44e6-8b49-5fb720ec6c01"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=409,
     *         description="Conflict - Cannot activate expired or exhausted promotion",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Cannot activate promotion that has expired or reached usage limit"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="is_expired", type="boolean", example=true),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2025-12-31 23:59:59"),
     *                 @OA\Property(property="hint", type="string", example="Update the end date to activate this promotion")
     *             ),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $activatedPromotion = $this->promotionService->activatePromotion($promotion);

        return ApiResponse::success(
            'Promotion activated successfully',
            new PromotionResource($activatedPromotion)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/promotions/{id}/deactivate",
     *     tags={"Tenant - Promotions"},
     *     summary="Deactivate a promotion",
     *     description="Deactivates an active promotion by setting is_active to false. Once deactivated, the promotion will no longer be available for use in POS or eCommerce systems and will not appear in active promotion queries. The promotion can be reactivated later using the activate endpoint. Deactivation is preferred over deletion for promotions that may be needed again or have usage history. Returns the complete updated promotion data with all status flags.",
     *     operationId="deactivatePromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID to deactivate",
     *         required=true,
     *         @OA\Schema(type="integer", example=4)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion deactivated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion deactivated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=4),
     *                 @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                 @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-04 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                 @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="display_priority", type="integer", example=6),
     *                 @OA\Property(property="is_active", type="boolean", example=false, description="Now set to false after deactivation"),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=true),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Inactive", description="Status updated to Inactive"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false, description="False - promotion cannot be applied when inactive"),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:47:36.000000Z", description="Timestamp reflects deactivation time")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:47:36.342019Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="07f2c219-3af4-4ede-a9eb-098e6aa81dd8"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $deactivatedPromotion = $this->promotionService->deactivatePromotion($promotion);

        return ApiResponse::success(
            'Promotion deactivated successfully',
            new PromotionResource($deactivatedPromotion)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions/{id}/products",
     *     tags={"Tenant - Promotions"},
     *     summary="Attach products to promotion",
     *     description="Attaches one or more products to a promotion. Supports attaching base products (variant_id=null) or specific product variants. Each product can be attached with or without a specific variant. If product_variant_id is null, the promotion applies to the base product. If specified, the promotion applies only to that specific variant. Multiple products can be attached in a single request.",
     *     operationId="attachProductsToPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Products to attach to the promotion",
     *         @OA\JsonContent(
     *             required={"products"},
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 description="Array of products to attach (minimum 1 product required)",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id"},
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="ID of the product to attach",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="product_variant_id",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID of specific product variant. If null, applies to base product",
     *                         example=null
     *                     )
     *                 ),
     *                 example={
     *                     {"product_id": 1, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": 2}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products attached to promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products attached to promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability showing newly attached products",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Samsung Galaxy A54 5G 128GB"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-SAMS-VTFM"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true, example=null)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object"), example={}),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"), example={})
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:18:30.139272Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1f2d7987-e407-4191-802f-b8baa87d4c47"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one product is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="products.0.product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected product does not exist.")
     *                 ),
     *                 @OA\Property(
     *                     property="products.0.product_variant_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected product variant does not exist.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->attachProducts($promotion, $request->validated()['products']);

        return ApiResponse::success(
            'Products attached to promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}/products/{productId}",
     *     tags={"Tenant - Promotions"},
     *     summary="Detach a product from promotion",
     *     description="Removes a specific product from a promotion's applicability. This detaches ALL variants of the specified product. If you need to remove specific variants only, they must be detached individually through variant-specific operations. After detachment, the promotion will no longer apply to this product. Returns the updated promotion with modified applicability.",
     *     operationId="detachProductFromPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID to detach from promotion",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product detached from promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product detached from promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability with detached product removed",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         description="Remaining attached products",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=4),
     *                             @OA\Property(property="name", type="string", example="TCL 55 4K UHD Smart LED TV"),
     *                             @OA\Property(property="sku", type="string", example="ELEC-DELL-56QT"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true, example=2)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object"), example={}),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"), example={})
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:21:15.553293Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="b4a58f29-b614-43d7-9bf9-cb1ef72bca47"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion or product not found, or product not attached to promotion",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->detachProduct($promotion, $productId);

        return ApiResponse::success(
            'Product detached from promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions/{id}/categories",
     *     tags={"Tenant - Promotions"},
     *     summary="Attach categories to promotion",
     *     description="Attaches one or more product categories to a promotion. When categories are attached, the promotion applies to all products within those categories. Categories apply to all products and variants within them - there is no variant-level filtering for category-based promotions. Multiple categories can be attached in a single request. Requires at least one category ID.",
     *     operationId="attachCategoriesToPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Categories to attach to the promotion",
     *         @OA\JsonContent(
     *             required={"category_ids"},
     *             @OA\Property(
     *                 property="category_ids",
     *                 type="array",
     *                 description="Array of category IDs to attach (minimum 1 category required)",
     *                 minItems=1,
     *                 @OA\Items(type="integer"),
     *                 example={5, 8, 12, 20}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Categories attached to promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Categories attached to promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability showing newly attached categories",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=5),
     *                             @OA\Property(property="name", type="string", example="Home & Living"),
     *                             @OA\Property(property="slug", type="string", example="home-living")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:25:31.621002Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="27ded940-48be-4844-a66c-24ac48adb03e"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="category_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one category is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="category_ids.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected category does not exist.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->attachCategories($promotion, $request->validated()['category_ids']);

        return ApiResponse::success(
            'Categories attached to promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}/categories/{categoryId}",
     *     tags={"Tenant - Promotions"},
     *     summary="Detach a category from promotion",
     *     description="Removes a specific product category from a promotion's applicability. After detachment, the promotion will no longer apply to products within this category. Returns the updated promotion with modified category applicability. If this was the only category attached, the categories array will be empty.",
     *     operationId="detachCategoryFromPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         description="Category ID to detach from promotion",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Category detached from promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Category detached from promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability with detached category removed",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         description="Remaining attached categories",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=8),
     *                             @OA\Property(property="name", type="string", example="Hospitality & Food Service"),
     *                             @OA\Property(property="slug", type="string", example="hospitality-food-service")
     *                         )
     *                     ),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:26:58.783267Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2609fc4a-4ac6-4b5b-9192-78079e43a7f2"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion or category not found, or category not attached to promotion",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->detachCategory($promotion, $categoryId);

        return ApiResponse::success(
            'Category detached from promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions/{id}/brands",
     *     tags={"Tenant - Promotions"},
     *     summary="Attach brands to promotion",
     *     description="Attaches one or more product brands to a promotion. When brands are attached, the promotion applies to all products of those brands. Brand-based promotions apply to all products and variants of the specified brands - there is no product or variant-level filtering. Multiple brands can be attached in a single request. Requires at least one brand ID.",
     *     operationId="attachBrandsToPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Brands to attach to the promotion",
     *         @OA\JsonContent(
     *             required={"brand_ids"},
     *             @OA\Property(
     *                 property="brand_ids",
     *                 type="array",
     *                 description="Array of brand IDs to attach (minimum 1 brand required)",
     *                 minItems=1,
     *                 @OA\Items(type="integer"),
     *                 example={2, 6, 9}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brands attached to promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brands attached to promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability showing newly attached brands",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Apple"),
     *                             @OA\Property(property="slug", type="string", example="apple")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:32:20.976213Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="763a875c-c708-4c14-afc3-2f8ae49bedae"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="brand_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one brand is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="brand_ids.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected brand does not exist.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->attachBrands($promotion, $request->validated()['brand_ids']);

        return ApiResponse::success(
            'Brands attached to promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}/brands/{brandId}",
     *     tags={"Tenant - Promotions"},
     *     summary="Detach a brand from promotion",
     *     description="Removes a specific product brand from a promotion's applicability. After detachment, the promotion will no longer apply to products of this brand. Returns the updated promotion with modified brand applicability. If this was the only brand attached, the brands array will be empty.",
     *     operationId="detachBrandFromPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="brandId",
     *         in="path",
     *         description="Brand ID to detach from promotion",
     *         required=true,
     *         @OA\Schema(type="integer", example=9)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Brand detached from promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand detached from promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with updated applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability with detached brand removed",
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         description="Remaining attached brands",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Apple"),
     *                             @OA\Property(property="slug", type="string", example="apple")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T13:33:16.215745Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ff0aff34-d474-4722-b864-e72606fe5cd2"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion or brand not found, or brand not attached to promotion",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->detachBrand($promotion, $brandId);

        return ApiResponse::success(
            'Brand detached from promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions/{id}/products/bulk",
     *     tags={"Tenant - Promotions"},
     *     summary="Bulk attach products to promotion",
     *     description="Attaches multiple products to a promotion in a single operation. This is the recommended approach for attaching large numbers of products efficiently. Supports attaching base products and specific product variants. Multiple instances of the same product with different variants can be attached. This operation is idempotent - products already attached will not cause errors.",
     *     operationId="bulkAttachProductsToPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Products to bulk attach to the promotion",
     *         @OA\JsonContent(
     *             required={"products"},
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 description="Array of products to attach. Can include same product with different variants",
     *                 minItems=1,
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id"},
     *                     @OA\Property(
     *                         property="product_id",
     *                         type="integer",
     *                         description="ID of the product to attach",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="product_variant_id",
     *                         type="integer",
     *                         nullable=true,
     *                         description="ID of specific product variant. If null, applies to base product",
     *                         example=null
     *                     )
     *                 ),
     *                 example={
     *                     {"product_id": 1, "product_variant_id": null},
     *                     {"product_id": 2, "product_variant_id": null},
     *                     {"product_id": 3, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": null},
     *                     {"product_id": 4, "product_variant_id": 2},
     *                     {"product_id": 4, "product_variant_id": 3}
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products bulk attached to promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products bulk attached to promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with all newly attached products in applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability showing all attached products including newly added ones",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         description="Complete list of attached products with base products and variants",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string"),
     *                             @OA\Property(property="sku", type="string"),
     *                             @OA\Property(property="variant_id", type="integer", nullable=true)
     *                         )
     *                     ),
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object"))
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T14:29:24.242725Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2a0c57f8-31ab-4458-b7b8-7e4a0e1268fb"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="products",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one product is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="products.0.product_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected product does not exist.")
     *                 ),
     *                 @OA\Property(
     *                     property="products.0.product_variant_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected product variant does not exist.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->bulkAttachProducts($promotion, $request->validated()['products']);

        return ApiResponse::success(
            'Products bulk attached to promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}/products/bulk",
     *     tags={"Tenant - Promotions"},
     *     summary="Bulk detach products from promotion",
     *     description="Removes multiple products from a promotion in a single operation. This is the recommended approach for detaching large numbers of products efficiently. When a product ID is provided, ALL variants and the base product entry for that product are removed from the promotion. This is a complete removal operation for specified product IDs. This operation is idempotent - products not currently attached will be silently ignored without causing errors.",
     *     operationId="bulkDetachProductsFromPromotion",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product IDs to bulk detach from the promotion",
     *         @OA\JsonContent(
     *             required={"product_ids"},
     *             @OA\Property(
     *                 property="product_ids",
     *                 type="array",
     *                 description="Array of product IDs to detach. All variants of these products will also be removed",
     *                 minItems=1,
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Products bulk detached from promotion successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products bulk detached from promotion successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 description="Complete promotion data with products removed from applicability",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", nullable=true, example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", nullable=true, example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", nullable=true, example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", nullable=true, example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="array", nullable=true, @OA\Items(type="string"), example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(property="applicable_store_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="applicability",
     *                     type="object",
     *                     description="Updated applicability with all specified products and their variants removed",
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         description="Empty array if all products were removed, or remaining products if partial detachment",
     *                         @OA\Items(type="object"),
     *                         example={}
     *                     ),
     *                     @OA\Property(
     *                         property="categories",
     *                         type="array",
     *                         description="Attached categories remain unchanged",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=8),
     *                             @OA\Property(property="name", type="string", example="Hospitality & Food Service"),
     *                             @OA\Property(property="slug", type="string", example="hospitality-food-service")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="brands",
     *                         type="array",
     *                         description="Attached brands remain unchanged",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Apple"),
     *                             @OA\Property(property="slug", type="string", example="apple")
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:24:04.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T14:16:55.750033Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="222c113b-d66d-4f39-8dfe-3df71543ddad"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Promotion not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Resource not found"),
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
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="product_ids",
     *                     type="array",
     *                     @OA\Items(type="string", example="At least one product is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="product_ids.0",
     *                     type="array",
     *                     @OA\Items(type="string", example="Selected product does not exist.")
     *                 )
     *             ),
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
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->bulkDetachProducts($promotion, $request->validated()['product_ids']);

        return ApiResponse::success(
            'Products bulk detached from promotion successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/promotions/{id}/stores",
     *     summary="Update applicable stores for a promotion",
     *     description="Updates the list of stores where a specific promotion is applicable",
     *     operationId="updatePromotionStores",
     *     tags={"Tenant - Promotions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Store IDs to associate with the promotion",
     *         @OA\JsonContent(
     *             required={"store_ids"},
     *             @OA\Property(
     *                 property="store_ids",
     *                 type="array",
     *                 description="Array of store IDs where the promotion should be applicable",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion applicable stores updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion applicable stores updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="Downtown Store Opening Special"),
     *                 @OA\Property(property="code", type="string", example="DOWNTOWN50"),
     *                 @OA\Property(property="description", type="string", example="Grand opening promotion for downtown location only"),
     *                 @OA\Property(property="promotion_type", type="string", example="fixed_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Fixed Amount Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="50.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="500.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example=null),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", example=2),
     *                 @OA\Property(property="total_usage_limit", type="integer", example=200),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=200),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-02-10 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-02-17 23:59:59"),
     *                 @OA\Property(property="active_days", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(property="applicable_customer_group_ids", type="array", nullable=true, @OA\Items(type="integer"), example=null),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=9),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=false),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="applicability", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:11:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T14:42:40.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T14:42:40.176719Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="82388c85-832f-450d-9537-d0848a30f81d"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateStores(UpdateStoresRequest $request, int $id): JsonResponse
    {
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->updateApplicableStores($promotion, $request->store_ids);

        return ApiResponse::success(
            'Promotion applicable stores updated successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/tenant/promotions/{id}/customer-groups",
     *     summary="Update applicable customer groups for a promotion",
     *     description="Updates the list of customer groups that a specific promotion is applicable to",
     *     operationId="updatePromotionCustomerGroups",
     *     tags={"Tenant - Promotions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Customer group IDs to associate with the promotion",
     *         @OA\JsonContent(
     *             required={"customer_group_ids"},
     *             @OA\Property(
     *                 property="customer_group_ids",
     *                 type="array",
     *                 description="Array of customer group IDs that the promotion should be applicable to",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion applicable customer groups updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion applicable customer groups updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="name", type="string", example="Downtown Store Opening Special"),
     *                 @OA\Property(property="code", type="string", example="DOWNTOWN50"),
     *                 @OA\Property(property="description", type="string", example="Grand opening promotion for downtown location only"),
     *                 @OA\Property(property="promotion_type", type="string", example="fixed_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Fixed Amount Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="50.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="500.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", nullable=true, example=null),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", example=2),
     *                 @OA\Property(property="total_usage_limit", type="integer", example=200),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=200),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-02-10 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-02-17 23:59:59"),
     *                 @OA\Property(property="active_days", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=9),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=false),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="applicability", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:11:22.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T14:46:17.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T14:46:17.235823Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="9a576b3b-d609-4838-9e87-bbf260c14f49"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateCustomerGroups(UpdateCustomerGroupsRequest $request, int $id): JsonResponse
    {
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->updateApplicableCustomerGroups($promotion, $request->customer_group_ids);

        return ApiResponse::success(
            'Promotion applicable customer groups updated successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tenant/promotions/{id}/banner",
     *     summary="Upload or update promotion banner image",
     *     description="Uploads a new banner image for a specific promotion",
     *     operationId="uploadPromotionBanner",
     *     tags={"Tenant - Promotions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Banner image file",
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"banner_image"},
     *                 @OA\Property(
     *                     property="banner_image",
     *                     type="string",
     *                     format="binary",
     *                     description="Banner image file to upload"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion banner updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion banner updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", example="promotions/banners/banner_receipt_1767539964.jpg"),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="applicability", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T15:19:24.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T15:19:24.320487Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4b38e231-c280-4814-a916-547840982f37"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function updateBanner(UpdatePromotionBannerRequest $request, int $id): JsonResponse
    {
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $bannerImage = $request->file('banner_image');
        $updatedPromotion = $this->promotionService->updateBanner($promotion, $bannerImage);

        return ApiResponse::success(
            'Promotion banner updated successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tenant/promotions/{id}/banner",
     *     summary="Remove promotion banner image",
     *     description="Deletes the banner image associated with a specific promotion",
     *     operationId="deletePromotionBanner",
     *     tags={"Tenant - Promotions"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Promotion ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Promotion banner removed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Promotion banner removed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Black Friday MEGA Sale"),
     *                 @OA\Property(property="code", type="string", example="BLACKFRIDAY2025"),
     *                 @OA\Property(property="description", type="string", example="Massive discounts on all products for Black Friday!"),
     *                 @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                 @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                 @OA\Property(property="discount_value", type="string", example="40.00"),
     *                 @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="get_items_free", type="boolean", example=true),
     *                 @OA\Property(property="get_items_discount_percentage", type="string", nullable=true, example=null),
     *                 @OA\Property(property="min_purchase_amount", type="string", example="1000.00"),
     *                 @OA\Property(property="max_discount_amount", type="string", example="8000.00"),
     *                 @OA\Property(property="max_uses_per_customer", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="total_usage_limit", type="integer", example=1000),
     *                 @OA\Property(property="total_usage_count", type="integer", example=0),
     *                 @OA\Property(property="remaining_usage", type="integer", example=1000),
     *                 @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-25 00:00:00"),
     *                 @OA\Property(property="end_date", type="string", format="date-time", example="2026-11-29 23:59:59"),
     *                 @OA\Property(property="active_days", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_days_formatted", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_start", type="string", nullable=true, example=null),
     *                 @OA\Property(property="active_time_end", type="string", nullable=true, example=null),
     *                 @OA\Property(property="time_window", type="string", nullable=true, example=null),
     *                 @OA\Property(
     *                     property="applicable_store_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(
     *                     property="applicable_customer_group_ids",
     *                     type="array",
     *                     @OA\Items(type="integer"),
     *                     example={1, 2}
     *                 ),
     *                 @OA\Property(property="applicable_to", type="string", example="all_products"),
     *                 @OA\Property(property="applicable_to_label", type="string", example="All Products"),
     *                 @OA\Property(property="show_on_website", type="boolean", example=true),
     *                 @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                 @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                 @OA\Property(property="display_priority", type="integer", example=30),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="auto_apply", type="boolean", example=true),
     *                 @OA\Property(property="is_expired", type="boolean", example=false),
     *                 @OA\Property(property="is_valid", type="boolean", example=false),
     *                 @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                 @OA\Property(property="is_active_now", type="boolean", example=false),
     *                 @OA\Property(property="status", type="string", example="Scheduled"),
     *                 @OA\Property(property="can_be_used", type="boolean", example=false),
     *                 @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                 @OA\Property(property="applicability", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="counts", type="array", @OA\Items(type="object"), example={}),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:02:07.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T15:22:51.000000Z")
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T15:22:51.582685Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="f62fa132-f25e-4868-a4cc-3b2e16d52bc1"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     )
     * )
     */
    public function removeBanner(int $id): JsonResponse
    {
        $promotion = $this->promotionService->getPromotionById($id);

        if (!$promotion) {
            return ApiResponse::notFound('Promotion not found');
        }

        $updatedPromotion = $this->promotionService->removeBanner($promotion);

        return ApiResponse::success(
            'Promotion banner removed successfully',
            new PromotionDetailResource($updatedPromotion)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions/active",
     *     tags={"Tenant - Promotions"},
     *     summary="Get currently active promotions",
     *     description="Retrieves all promotions that are currently active and can be used right now. Filters promotions based on current date/time, active status, and validity period. Optionally filters by store if store_id is provided. Returns a simplified promotion data structure optimized for real-time application in POS and checkout systems.",
     *     operationId="getActivePromotions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter promotions applicable to specific store. If not provided, returns promotions for all stores or store-agnostic promotions",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Active promotions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Active promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of currently active promotions sorted by display priority (descending)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                     @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                     @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                     @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                     @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                     @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_items_free", type="boolean", example=true),
     *                     @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                     @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                     @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                     @OA\Property(property="auto_apply", type="boolean", example=true, description="Whether promotion should be automatically applied at checkout"),
     *                     @OA\Property(property="display_priority", type="integer", example=6),
     *                     @OA\Property(property="banner_image_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:34:29.509115Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="1197045b-48c8-4fd3-ba8f-08193dab734c"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function activePromotions(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id');

        $promotions = $this->promotionService->getCurrentlyRunning($storeId);

        return ApiResponse::success(
            'Active promotions retrieved successfully',
            ActivePromotionResource::collection($promotions)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions/featured",
     *     tags={"Tenant - Promotions"},
     *     summary="Get featured promotions",
     *     description="Retrieves promotions that are marked as featured and currently active. Featured promotions are typically highlighted in storefronts, marketing materials, and promotional banners. Returns promotions sorted by display priority with complete status and usage information. Optionally filters by store.",
     *     operationId="getFeaturedPromotions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter promotions applicable to specific store. If not provided, returns featured promotions for all stores",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Featured promotions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Featured promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of featured promotions sorted by display priority (descending)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                     @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                     @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                     @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                     @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                     @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_items_free", type="boolean", example=true),
     *                     @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                     @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                     @OA\Property(property="total_usage_count", type="integer", example=0),
     *                     @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-04 00:00:00"),
     *                     @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                     @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                     @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                     @OA\Property(property="show_on_website", type="boolean", example=true),
     *                     @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                     @OA\Property(property="display_priority", type="integer", example=6),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="auto_apply", type="boolean", example=true),
     *                     @OA\Property(property="is_expired", type="boolean", example=false, description="Whether promotion end date has passed"),
     *                     @OA\Property(property="is_valid", type="boolean", example=true, description="Whether current date/time is within promotion validity period"),
     *                     @OA\Property(property="is_exhausted", type="boolean", example=false, description="Whether promotion usage limit has been reached"),
     *                     @OA\Property(property="status", type="string", example="Active", description="Human-readable status: Active, Scheduled, Expired, Exhausted, Inactive"),
     *                     @OA\Property(property="can_be_used", type="boolean", example=true, description="Whether promotion can currently be applied to orders"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true, description="Whether promotion can be edited"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:36:37.464154Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2e50fc7e-a2f8-4fcd-b4d0-98ff32f89d32"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function featuredPromotions(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id');

        $promotions = $this->promotionService->getFeaturedPromotions($storeId);

        return ApiResponse::success(
            'Featured promotions retrieved successfully',
            PromotionResource::collection($promotions)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions/pos",
     *     tags={"Tenant - Promotions"},
     *     summary="Get POS-enabled promotions",
     *     description="Retrieves all promotions that are enabled for display and usage in Point of Sale (POS) systems. Returns currently active promotions with show_in_pos flag set to true. Includes complete promotion details, status flags, and usage information needed for POS checkout operations. Optionally filters by specific store.",
     *     operationId="getPOSPromotions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter promotions applicable to specific store. If not provided, returns POS promotions for all stores",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="POS promotions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="POS promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of POS-enabled promotions sorted by display priority (descending)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                     @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                     @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                     @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                     @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                     @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_items_free", type="boolean", example=true),
     *                     @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                     @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                     @OA\Property(property="total_usage_count", type="integer", example=0, description="Number of times promotion has been used"),
     *                     @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null, description="Maximum total uses allowed"),
     *                     @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null, description="Remaining uses before limit reached"),
     *                     @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-04 00:00:00"),
     *                     @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                     @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                     @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                     @OA\Property(property="show_on_website", type="boolean", example=true),
     *                     @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                     @OA\Property(property="display_priority", type="integer", example=6),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="auto_apply", type="boolean", example=true, description="Whether to automatically apply at POS checkout"),
     *                     @OA\Property(property="is_expired", type="boolean", example=false),
     *                     @OA\Property(property="is_valid", type="boolean", example=true),
     *                     @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                     @OA\Property(property="status", type="string", example="Active"),
     *                     @OA\Property(property="can_be_used", type="boolean", example=true, description="Whether promotion can be applied to transactions right now"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:39:49.901959Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="2d5a4c58-6566-4ecd-a52e-18ef63156a75"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function posPromotions(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id');

        $promotions = $this->promotionService->getPosPromotions($storeId);

        return ApiResponse::success(
            'POS promotions retrieved successfully',
            PromotionResource::collection($promotions)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/promotions/website",
     *     tags={"Tenant - Promotions"},
     *     summary="Get website-enabled promotions",
     *     description="Retrieves all promotions that are enabled for display and usage on the tenant's eCommerce website. Returns currently active promotions with show_on_website flag set to true. Includes complete promotion details, status flags, and usage information needed for online checkout and promotional displays. Optionally filters by specific store for multi-location businesses.",
     *     operationId="getWebsitePromotions",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter promotions applicable to specific store. If not provided, returns website promotions for all stores",
     *         required=false,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Website promotions retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Website promotions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 description="List of website-enabled promotions sorted by display priority (descending)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=4),
     *                     @OA\Property(property="name", type="string", example="Weekend Brand Sale"),
     *                     @OA\Property(property="code", type="string", example="WEEKEND20"),
     *                     @OA\Property(property="description", type="string", nullable=true, example="20% off on selected brands every weekend"),
     *                     @OA\Property(property="promotion_type", type="string", example="percentage_discount"),
     *                     @OA\Property(property="promotion_type_label", type="string", example="Percentage Discount"),
     *                     @OA\Property(property="discount_value", type="string", nullable=true, example="20.00"),
     *                     @OA\Property(property="buy_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_quantity", type="integer", nullable=true, example=null),
     *                     @OA\Property(property="get_items_free", type="boolean", example=true),
     *                     @OA\Property(property="min_purchase_amount", type="string", nullable=true, example=null),
     *                     @OA\Property(property="max_discount_amount", type="string", nullable=true, example="3000.00"),
     *                     @OA\Property(property="total_usage_count", type="integer", example=0, description="Number of times promotion has been used"),
     *                     @OA\Property(property="total_usage_limit", type="integer", nullable=true, example=null, description="Maximum total uses allowed"),
     *                     @OA\Property(property="remaining_usage", type="integer", nullable=true, example=null, description="Remaining uses before limit reached"),
     *                     @OA\Property(property="start_date", type="string", format="date-time", example="2026-01-04 00:00:00"),
     *                     @OA\Property(property="end_date", type="string", format="date-time", example="2026-12-31 23:59:59"),
     *                     @OA\Property(property="applicable_to", type="string", example="specific_brands"),
     *                     @OA\Property(property="applicable_to_label", type="string", example="Specific Brands"),
     *                     @OA\Property(property="show_on_website", type="boolean", example=true),
     *                     @OA\Property(property="show_in_pos", type="boolean", example=true),
     *                     @OA\Property(property="display_priority", type="integer", example=6),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="auto_apply", type="boolean", example=true, description="Whether to automatically apply at online checkout"),
     *                     @OA\Property(property="is_expired", type="boolean", example=false),
     *                     @OA\Property(property="is_valid", type="boolean", example=true),
     *                     @OA\Property(property="is_exhausted", type="boolean", example=false),
     *                     @OA\Property(property="status", type="string", example="Active"),
     *                     @OA\Property(property="can_be_used", type="boolean", example=true, description="Whether promotion can be applied to online orders right now"),
     *                     @OA\Property(property="can_be_edited", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-01-04T12:09:01.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-01-04T12:41:09.709423Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ad5c33cf-8faf-4f28-98c3-9aedc014d8ab"),
     *                 @OA\Property(property="tenant_id", type="string", format="uuid", example="bbab2597-e1ae-466b-a071-83033841d2ed"),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This action is unauthorized."),
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
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
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
    public function websitePromotions(Request $request): JsonResponse
    {
        $storeId = $request->integer('store_id');

        $promotions = $this->promotionService->getWebsitePromotions($storeId);

        return ApiResponse::success(
            'Website promotions retrieved successfully',
            PromotionResource::collection($promotions)
        );
    }
}
