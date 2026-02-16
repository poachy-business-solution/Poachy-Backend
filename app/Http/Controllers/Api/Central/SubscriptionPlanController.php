<?php

namespace App\Http\Controllers\Api\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ListSubscriptionPlansRequest;
use App\Http\Resources\Central\SubscriptionPlanResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\Shared\SubscriptionPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionPlanService $subscriptionPlanService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/central/subscription-plans",
     *     summary="Get all subscription plans",
     *     description="Retrieves a list of all available subscription plans with optional filtering and sorting. This endpoint is public and does not require authentication.",
     *     tags={"Central - Subscription Plans"},
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="is_featured",
     *         in="query",
     *         description="Filter by featured status",
     *         required=false,
     *         @OA\Schema(type="boolean"),
     *         example=true
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float"),
     *         example=2500
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter (must be greater than min_price)",
     *         required=false,
     *         @OA\Schema(type="number", format="float"),
     *         example=10000
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort field",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"price", "name", "created_at", "billing_cycle_days"}
     *         ),
     *         example="price"
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             enum={"asc", "desc"}
     *         ),
     *         example="asc"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plans retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plans retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Free"),
     *                     @OA\Property(property="slug", type="string", example="free"),
     *                     @OA\Property(property="description", type="string", example="Perfect for getting started with basic POS features"),
     *                     @OA\Property(
     *                         property="pricing",
     *                         type="object",
     *                         @OA\Property(property="price", type="number", format="float", example=0),
     *                         @OA\Property(property="currency", type="string", example="KES"),
     *                         @OA\Property(property="billing_cycle_days", type="integer", example=0),
     *                         @OA\Property(property="billing_cycle_display", type="string", example="Lifetime"),
     *                         @OA\Property(property="is_free", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="features",
     *                         type="object",
     *                         @OA\Property(property="support", type="string", example="community"),
     *                         @OA\Property(property="max_users", type="integer", example=2),
     *                         @OA\Property(property="max_products", type="integer", example=50),
     *                         @OA\Property(property="max_locations", type="integer", example=1),
     *                         @OA\Property(property="enable_reports", type="string", example="basic"),
     *                         @OA\Property(property="barcode_scanning", type="boolean", example=false),
     *                         @OA\Property(property="enable_analytics", type="boolean", example=false),
     *                         @OA\Property(property="enable_ecommerce", type="boolean", example=false),
     *                         @OA\Property(property="enable_marketplace", type="boolean", example=false),
     *                         @OA\Property(property="inventory_tracking", type="boolean", example=true),
     *                         @OA\Property(property="customer_management", type="string", example="basic"),
     *                         @OA\Property(property="transaction_fee_percent", type="number", format="float", example=0),
     *                         @OA\Property(property="max_transactions_per_month", type="integer", example=100)
     *                     ),
     *                     @OA\Property(
     *                         property="feature_highlights",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="category", type="string", example="Products"),
     *                             @OA\Property(property="value", oneOf={
     *                                 @OA\Schema(type="integer"),
     *                                 @OA\Schema(type="string"),
     *                                 @OA\Schema(type="boolean")
     *                             }, example=50),
     *                             @OA\Property(property="display", type="string", example="Up to 50 Products")
     *                         )
     *                     ),
     *                     @OA\Property(
     *                         property="status",
     *                         type="object",
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_featured", type="boolean", example=false)
     *                     ),
     *                     @OA\Property(
     *                         property="popularity",
     *                         type="object",
     *                         @OA\Property(property="active_subscriptions_count", type="integer", example=0),
     *                         @OA\Property(property="is_popular", type="boolean", example=false)
     *                     ),
     *                     @OA\Property(
     *                         property="metadata",
     *                         type="object",
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-28T11:32:15.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-28T11:32:15.000000Z")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T13:46:46.229192Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="fb822aaf-d31d-4358-9230-4cafed16445f"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Subscription plans retrieved successfully",
     *                 "data": {
     *                     {
     *                         "id": 1,
     *                         "name": "Free",
     *                         "slug": "free",
     *                         "description": "Perfect for getting started with basic POS features",
     *                         "pricing": {
     *                             "price": 0,
     *                             "currency": "KES",
     *                             "billing_cycle_days": 0,
     *                             "billing_cycle_display": "Lifetime",
     *                             "is_free": true
     *                         },
     *                         "features": {
     *                             "support": "community",
     *                             "max_users": 2,
     *                             "max_products": 50,
     *                             "max_locations": 1,
     *                             "enable_reports": "basic",
     *                             "barcode_scanning": false,
     *                             "enable_analytics": false,
     *                             "enable_ecommerce": false,
     *                             "enable_marketplace": false,
     *                             "inventory_tracking": true,
     *                             "customer_management": "basic",
     *                             "transaction_fee_percent": 0,
     *                             "max_transactions_per_month": 100
     *                         },
     *                         "feature_highlights": {
     *                             {
     *                                 "category": "Products",
     *                                 "value": 50,
     *                                 "display": "Up to 50 Products"
     *                             },
     *                             {
     *                                 "category": "Users",
     *                                 "value": 2,
     *                                 "display": "Up to 2 Users"
     *                             },
     *                             {
     *                                 "category": "Locations",
     *                                 "value": 1,
     *                                 "display": "1 Store Location"
     *                             },
     *                             {
     *                                 "category": "Transactions",
     *                                 "value": 100,
     *                                 "display": "100 Transactions/Month"
     *                             },
     *                             {
     *                                 "category": "Support",
     *                                 "value": "community",
     *                                 "display": "Community Support"
     *                             },
     *                             {
     *                                 "category": "Transaction Fee",
     *                                 "value": 0,
     *                                 "display": "No Transaction Fees"
     *                             }
     *                         },
     *                         "status": {
     *                             "is_active": true,
     *                             "is_featured": false
     *                         },
     *                         "popularity": {
     *                             "active_subscriptions_count": 0,
     *                             "is_popular": false
     *                         },
     *                         "metadata": {
     *                             "created_at": "2025-11-28T11:32:15.000000Z",
     *                             "updated_at": "2025-11-28T11:32:15.000000Z"
     *                         }
     *                     },
     *                     {
     *                         "id": 2,
     *                         "name": "Basic",
     *                         "slug": "basic",
     *                         "description": "Essential features for growing businesses",
     *                         "pricing": {
     *                             "price": 2500,
     *                             "currency": "KES",
     *                             "billing_cycle_days": 30,
     *                             "billing_cycle_display": "Monthly",
     *                             "is_free": false
     *                         },
     *                         "features": {
     *                             "support": "email",
     *                             "max_users": 5,
     *                             "max_products": 500,
     *                             "max_locations": 2,
     *                             "enable_reports": "standard",
     *                             "loyalty_program": false,
     *                             "barcode_scanning": true,
     *                             "enable_analytics": "basic",
     *                             "enable_ecommerce": true,
     *                             "enable_marketplace": true,
     *                             "inventory_tracking": true,
     *                             "customer_management": "standard",
     *                             "transaction_fee_percent": 2,
     *                             "max_transactions_per_month": 1000
     *                         },
     *                         "feature_highlights": {
     *                             {
     *                                 "category": "Products",
     *                                 "value": 500,
     *                                 "display": "Up to 500 Products"
     *                             },
     *                             {
     *                                 "category": "Users",
     *                                 "value": 5,
     *                                 "display": "Up to 5 Users"
     *                             },
     *                             {
     *                                 "category": "E-commerce",
     *                                 "value": true,
     *                                 "display": "Online Store Enabled"
     *                             },
     *                             {
     *                                 "category": "Marketplace",
     *                                 "value": true,
     *                                 "display": "Marketplace Access"
     *                             }
     *                         },
     *                         "status": {
     *                             "is_active": true,
     *                             "is_featured": true
     *                         },
     *                         "popularity": {
     *                             "active_subscriptions_count": 0,
     *                             "is_popular": true
     *                         },
     *                         "metadata": {
     *                             "created_at": "2025-11-28T11:32:15.000000Z",
     *                             "updated_at": "2025-11-28T11:32:15.000000Z"
     *                         }
     *                     },
     *                     {
     *                         "id": 4,
     *                         "name": "Enterprise",
     *                         "slug": "enterprise",
     *                         "description": "Complete solution for large-scale operations",
     *                         "pricing": {
     *                             "price": 15000,
     *                             "currency": "KES",
     *                             "billing_cycle_days": 30,
     *                             "billing_cycle_display": "Monthly",
     *                             "is_free": false
     *                         },
     *                         "features": {
     *                             "support": "dedicated",
     *                             "max_users": "unlimited",
     *                             "api_access": true,
     *                             "white_label": true,
     *                             "max_products": "unlimited",
     *                             "max_locations": "unlimited",
     *                             "enable_reports": "custom",
     *                             "multi_currency": true,
     *                             "loyalty_program": true,
     *                             "barcode_scanning": true,
     *                             "enable_analytics": "enterprise",
     *                             "enable_ecommerce": true,
     *                             "enable_marketplace": true,
     *                             "custom_integrations": true,
     *                             "transaction_fee_percent": 1,
     *                             "max_transactions_per_month": "unlimited"
     *                         },
     *                         "feature_highlights": {
     *                             {
     *                                 "category": "Products",
     *                                 "value": "unlimited",
     *                                 "display": "Unlimited Products"
     *                             },
     *                             {
     *                                 "category": "Users",
     *                                 "value": "unlimited",
     *                                 "display": "Unlimited Users"
     *                             },
     *                             {
     *                                 "category": "Locations",
     *                                 "value": "unlimited",
     *                                 "display": "Unlimited Locations"
     *                             }
     *                         },
     *                         "status": {
     *                             "is_active": true,
     *                             "is_featured": false
     *                         },
     *                         "popularity": {
     *                             "active_subscriptions_count": 0,
     *                             "is_popular": false
     *                         },
     *                         "metadata": {
     *                             "created_at": "2025-11-28T11:32:15.000000Z",
     *                             "updated_at": "2025-11-28T11:32:15.000000Z"
     *                         }
     *                     }
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T13:46:46.229192Z",
     *                     "request_id": "fb822aaf-d31d-4358-9230-4cafed16445f",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
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
     *                     property="max_price",
     *                     type="array",
     *                     @OA\Items(type="string", example="The max price must be greater than min price.")
     *                 ),
     *                 @OA\Property(
     *                     property="sort_by",
     *                     type="array",
     *                     @OA\Items(type="string", example="The selected sort by is invalid.")
     *                 )
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
    public function index(ListSubscriptionPlansRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $plans = $this->subscriptionPlanService->listPlans($filters);

        return ApiResponse::success(
            message: 'Subscription plans retrieved successfully',
            data: SubscriptionPlanResource::collection($plans)
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/central/subscription-plans/{slug}",
     *     summary="Get subscription plan by slug",
     *     description="Retrieves detailed information about a specific subscription plan using its slug identifier. This endpoint is public and does not require authentication.",
     *     tags={"Central - Subscription Plans"},
     *     @OA\Parameter(
     *         name="slug",
     *         in="path",
     *         description="The unique slug identifier of the subscription plan (e.g., 'free', 'basic', 'premium', 'enterprise')",
     *         required=true,
     *         @OA\Schema(type="string"),
     *         example="basic"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subscription plan retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Subscription plan retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Basic"),
     *                 @OA\Property(property="slug", type="string", example="basic"),
     *                 @OA\Property(property="description", type="string", example="Essential features for growing businesses"),
     *                 @OA\Property(
     *                     property="pricing",
     *                     type="object",
     *                     @OA\Property(property="price", type="number", format="float", example=2500),
     *                     @OA\Property(property="currency", type="string", example="KES"),
     *                     @OA\Property(property="billing_cycle_days", type="integer", example=30),
     *                     @OA\Property(property="billing_cycle_display", type="string", example="Monthly"),
     *                     @OA\Property(property="is_free", type="boolean", example=false)
     *                 ),
     *                 @OA\Property(
     *                     property="features",
     *                     type="object",
     *                     @OA\Property(property="support", type="string", example="email"),
     *                     @OA\Property(property="max_users", type="integer", example=5),
     *                     @OA\Property(property="max_products", type="integer", example=500),
     *                     @OA\Property(property="max_locations", type="integer", example=2),
     *                     @OA\Property(property="enable_reports", type="string", example="standard"),
     *                     @OA\Property(property="loyalty_program", type="boolean", example=false),
     *                     @OA\Property(property="barcode_scanning", type="boolean", example=true),
     *                     @OA\Property(property="enable_analytics", type="string", example="basic"),
     *                     @OA\Property(property="enable_ecommerce", type="boolean", example=true),
     *                     @OA\Property(property="enable_marketplace", type="boolean", example=true),
     *                     @OA\Property(property="inventory_tracking", type="boolean", example=true),
     *                     @OA\Property(property="customer_management", type="string", example="standard"),
     *                     @OA\Property(property="transaction_fee_percent", type="number", format="float", example=2),
     *                     @OA\Property(property="max_transactions_per_month", type="integer", example=1000)
     *                 ),
     *                 @OA\Property(
     *                     property="feature_highlights",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="category", type="string", example="Products"),
     *                         @OA\Property(property="value", oneOf={
     *                             @OA\Schema(type="integer"),
     *                             @OA\Schema(type="string"),
     *                             @OA\Schema(type="boolean")
     *                         }, example=500),
     *                         @OA\Property(property="display", type="string", example="Up to 500 Products")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="status",
     *                     type="object",
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="is_featured", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="popularity",
     *                     type="object",
     *                     @OA\Property(property="active_subscriptions_count", type="integer", example=0),
     *                     @OA\Property(property="is_popular", type="boolean", example=true)
     *                 ),
     *                 @OA\Property(
     *                     property="metadata",
     *                     type="object",
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-11-28T11:32:15.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-11-28T11:32:15.000000Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T14:02:20.429314Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="14d6899d-247a-4ac8-bd28-c7cdacc9bf6e"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": true,
     *                 "message": "Subscription plan retrieved successfully",
     *                 "data": {
     *                     "id": 2,
     *                     "name": "Basic",
     *                     "slug": "basic",
     *                     "description": "Essential features for growing businesses",
     *                     "pricing": {
     *                         "price": 2500,
     *                         "currency": "KES",
     *                         "billing_cycle_days": 30,
     *                         "billing_cycle_display": "Monthly",
     *                         "is_free": false
     *                     },
     *                     "features": {
     *                         "support": "email",
     *                         "max_users": 5,
     *                         "max_products": 500,
     *                         "max_locations": 2,
     *                         "enable_reports": "standard",
     *                         "loyalty_program": false,
     *                         "barcode_scanning": true,
     *                         "enable_analytics": "basic",
     *                         "enable_ecommerce": true,
     *                         "enable_marketplace": true,
     *                         "inventory_tracking": true,
     *                         "customer_management": "standard",
     *                         "transaction_fee_percent": 2,
     *                         "max_transactions_per_month": 1000
     *                     },
     *                     "feature_highlights": {
     *                         {
     *                             "category": "Products",
     *                             "value": 500,
     *                             "display": "Up to 500 Products"
     *                         },
     *                         {
     *                             "category": "Users",
     *                             "value": 5,
     *                             "display": "Up to 5 Users"
     *                         },
     *                         {
     *                             "category": "Locations",
     *                             "value": 2,
     *                             "display": "2 Store Locations"
     *                         },
     *                         {
     *                             "category": "Transactions",
     *                             "value": 1000,
     *                             "display": "1,000 Transactions/Month"
     *                         },
     *                         {
     *                             "category": "E-commerce",
     *                             "value": true,
     *                             "display": "Online Store Enabled"
     *                         },
     *                         {
     *                             "category": "Marketplace",
     *                             "value": true,
     *                             "display": "Marketplace Access"
     *                         },
     *                         {
     *                             "category": "Analytics",
     *                             "value": "basic",
     *                             "display": "Basic Analytics"
     *                         },
     *                         {
     *                             "category": "Support",
     *                             "value": "email",
     *                             "display": "Email Support"
     *                         },
     *                         {
     *                             "category": "Transaction Fee",
     *                             "value": 2,
     *                             "display": "2% Transaction Fee"
     *                         }
     *                     },
     *                     "status": {
     *                         "is_active": true,
     *                         "is_featured": true
     *                     },
     *                     "popularity": {
     *                         "active_subscriptions_count": 0,
     *                         "is_popular": true
     *                     },
     *                     "metadata": {
     *                         "created_at": "2025-11-28T11:32:15.000000Z",
     *                         "updated_at": "2025-11-28T11:32:15.000000Z"
     *                     }
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T14:02:20.429314Z",
     *                     "request_id": "14d6899d-247a-4ac8-bd28-c7cdacc9bf6e",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Subscription plan not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Subscription plan not found"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="slug",
     *                     type="string",
     *                     example="No subscription plan found with name: test"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2025-12-14T14:04:28.800380Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="ecbce224-d1a8-448d-80c0-d20194b1fcc7"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             ),
     *             example={
     *                 "success": false,
     *                 "message": "Subscription plan not found",
     *                 "errors": {
     *                     "slug": "No subscription plan found with name: test"
     *                 },
     *                 "meta": {
     *                     "timestamp": "2025-12-14T14:04:28.800380Z",
     *                     "request_id": "ecbce224-d1a8-448d-80c0-d20194b1fcc7",
     *                     "tenant_id": null,
     *                     "tenant_name": null
     *                 }
     *             }
     *         )
     *     )
     * )
     */
    public function show(string $slug): JsonResponse
    {
        $plan = $this->subscriptionPlanService->getPlanBySlug($slug);

        if (!$plan) {
            return ApiResponse::notFound(
                message: 'Subscription plan not found',
                errors: ['slug' => "No subscription plan found with name: {$slug}"]
            );
        }

        return ApiResponse::success(
            message: 'Subscription plan retrieved successfully',
            data: new SubscriptionPlanResource($plan)
        );
    }
}
