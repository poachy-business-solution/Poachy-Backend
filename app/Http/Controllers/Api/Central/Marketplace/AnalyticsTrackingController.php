<?php

namespace App\Http\Controllers\Api\Central\Marketplace;

use App\Enums\Central\TrackEvent;
use App\Helpers\CustomerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Marketplace\Analytics\TrackEventRequest;
use App\Http\Requests\Central\Marketplace\Analytics\TrackProductViewRequest;
use App\Http\Requests\Central\Marketplace\Analytics\TrackSearchRequest;
use App\Http\Responses\ApiResponse;
use App\Models\CustomerJourneyEvent;
use App\Models\ProductPageView;
use App\Models\SearchQuery;
use App\Services\Central\Marketplace\Analytics\AnalyticsTrackingService;
use Illuminate\Http\JsonResponse;

class AnalyticsTrackingController extends Controller
{
    public function __construct(
        private readonly AnalyticsTrackingService $analyticsService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/analytics/product-view",
     *     summary="Track product view event",
     *     description="Records a product view event for analytics. Tracks referrer source, search queries, device info, and user engagement. Includes deduplication logic - returns deduplicated=true if same session viewed product recently. No authentication required for public tracking.",
     *     operationId="trackProductView",
     *     tags={"Central - Analytics - Marketplace"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product view tracking data",
     *         @OA\JsonContent(
     *             required={"product_id", "session_id"},
     *             @OA\Property(
     *                 property="product_id",
     *                 type="integer",
     *                 description="Marketplace product ID being viewed",
     *                 example=2
     *             ),
     *             @OA\Property(
     *                 property="session_id",
     *                 type="string",
     *                 format="uuid",
     *                 description="Unique session identifier",
     *                 example="550e8400-e29b-41d4-a716-446655440000"
     *             ),
     *             @OA\Property(
     *                 property="referrer_source",
     *                 type="string",
     *                 description="Source that led to product view",
     *                 enum={"search", "category", "home", "external"},
     *                 example="search",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="referrer_url",
     *                 type="string",
     *                 format="uri",
     *                 description="URL of the referring page",
     *                 example="https://yourstore.com/search?q=shoes",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="search_query",
     *                 type="string",
     *                 description="Search query that led to this view (if from search)",
     *                 example="running shoes",
     *                 maxLength=255,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="device_type",
     *                 type="string",
     *                 description="Device type of the viewer",
     *                 enum={"mobile", "tablet", "desktop"},
     *                 example="mobile",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="browser",
     *                 type="string",
     *                 description="Browser name",
     *                 example="Chrome",
     *                 maxLength=100,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="platform",
     *                 type="string",
     *                 description="Operating system/platform",
     *                 example="Android",
     *                 maxLength=100,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="time_spent_seconds",
     *                 type="integer",
     *                 description="Time spent viewing product (for updates)",
     *                 example=8,
     *                 minimum=0,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="scrolled_to_description",
     *                 type="boolean",
     *                 description="Whether user scrolled to product description",
     *                 example=false,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="scrolled_to_reviews",
     *                 type="boolean",
     *                 description="Whether user scrolled to reviews section",
     *                 example=false,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="clicked_images",
     *                 type="boolean",
     *                 description="Whether user clicked on product images",
     *                 example=false,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product view tracked or deduplicated",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="New view tracked successfully",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Product view tracked successfully"),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T14:46:53.951596Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="258255d1-63fe-400f-8742-64e675672061"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 ),
     *                 @OA\Schema(
     *                     description="View deduplicated - already tracked recently",
     *                     @OA\Property(property="success", type="boolean", example=true),
     *                     @OA\Property(property="message", type="string", example="Product view already tracked recently"),
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="deduplicated", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T14:46:01.844942Z"),
     *                         @OA\Property(property="request_id", type="string", format="uuid", example="ed44ae4a-e980-477d-8d74-1bae4b58213e"),
     *                         @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                         @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *                     )
     *                 )
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
    public function trackProductView(TrackProductViewRequest $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomer();

        // Deduplication: Check for duplicate within last 5 minutes
        $existingView = ProductPageView::on('central')
            ->where('session_id', $request->session_id)
            ->where('marketplace_product_id', $request->product_id)
            ->where('viewed_at', '>', now()->subMinutes(5))
            ->first();

        if ($existingView) {
            return ApiResponse::success('Product view already tracked recently', [
                // 'id'           => $existingView->id,
                'deduplicated' => true,
            ]);
        }

        $view = $this->analyticsService->trackProductView([
            'marketplace_product_id' => $request->product_id,
            'customer_id'            => $customer?->id,
            'session_id'             => $request->session_id,
            'referrer_source'        => $request->referrer_source,
            'referrer_url'           => $request->referrer_url,
            'search_query'           => $request->search_query,
            'device_type'            => $request->device_type,
            'browser'                => $request->browser,
            'platform'               => $request->platform,
            'ip_address'             => $request->ip(),
            'user_agent'             => $request->userAgent(),
            'viewed_at'              => now(),
        ]);

        return ApiResponse::success('Product view tracked successfully');
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/central/marketplace/analytics/product-view/{sessionId}/{productId}",
     *     summary="Update product view engagement metrics",
     *     description="Updates engagement metrics for an existing product view tracking record. Used to record time spent, scrolling behavior, and image interactions as the user engages with the product page. No authentication required.",
     *     operationId="updateProductView",
     *     tags={"Central - Analytics - Marketplace"},
     *     @OA\Parameter(
     *         name="sessionId",
     *         in="path",
     *         description="Session UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000")
     *     ),
     *     @OA\Parameter(
     *         name="productId",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         description="Engagement metrics to update",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="time_spent_seconds",
     *                 type="integer",
     *                 description="Total time spent viewing the product",
     *                 example=8,
     *                 minimum=0
     *             ),
     *             @OA\Property(
     *                 property="scrolled_to_description",
     *                 type="boolean",
     *                 description="Whether user scrolled to product description",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="scrolled_to_reviews",
     *                 type="boolean",
     *                 description="Whether user scrolled to reviews section",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="clicked_images",
     *                 type="boolean",
     *                 description="Whether user clicked on product images",
     *                 example=false
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product view updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product view updated successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T15:03:07.990781Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="a417e502-a2aa-4109-9a14-c3b07393b3a7"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product view record not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Product view record not found."),
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
    public function updateProductView(
        string $sessionId,
        int $productId,
        TrackProductViewRequest $request
    ): JsonResponse {
        $view = ProductPageView::on('central')
            ->where('session_id', $sessionId)
            ->where('marketplace_product_id', $productId)
            ->orderByDesc('viewed_at')
            ->first();

        if (! $view) {
            return ApiResponse::notFound('Product view not found');
        }

        $view->update($request->only([
            'time_spent_seconds',
            'scrolled_to_description',
            'scrolled_to_reviews',
            'clicked_images',
        ]));

        return ApiResponse::success('Product view updated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/analytics/search",
     *     summary="Track search query event",
     *     description="Records a search query event for analytics. Tracks search terms, results count, applied filters, and filter refinements. Supports parent_search_id to track filter refinement chains. No authentication required for public tracking.",
     *     operationId="trackSearchQuery",
     *     tags={"Central - Analytics - Marketplace"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Search query tracking data",
     *         @OA\JsonContent(
     *             required={"search_query", "session_id", "results_count"},
     *             @OA\Property(
     *                 property="search_query",
     *                 type="string",
     *                 description="The search term entered by the user",
     *                 example="running shoes",
     *                 maxLength=255
     *             ),
     *             @OA\Property(
     *                 property="session_id",
     *                 type="string",
     *                 format="uuid",
     *                 description="Unique session identifier",
     *                 example="550e8400-e29b-41d4-a716-446655440000"
     *             ),
     *             @OA\Property(
     *                 property="results_count",
     *                 type="integer",
     *                 description="Number of search results returned",
     *                 example=24,
     *                 minimum=0
     *             ),
     *             @OA\Property(
     *                 property="filters_applied",
     *                 type="object",
     *                 description="Any filters applied to the search",
     *                 example={"category": "men", "brand": "Nike", "price_min": 50, "price_max": 150},
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="parent_search_id",
     *                 type="integer",
     *                 description="ID of the parent search if this is a filter refinement",
     *                 example=123,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Search query tracked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Search query tracked successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T15:11:53.251002Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="e99edb73-33c3-41bc-bc26-44981dda94e5"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
    public function trackSearch(TrackSearchRequest $request): JsonResponse
    {
        $customer = CustomerHelper::getAuthenticatedCustomer();

        // Deduplication: Allow same query from same session if >1 minute apart
        $recentSearch = SearchQuery::on('central')
            ->where('session_id', $request->session_id)
            ->where('search_query', $request->search_query)
            ->where('searched_at', '>', now()->subMinute())
            ->first();

        if ($recentSearch) {
            return ApiResponse::success('Search query already tracked recently', [
                // 'id'           => $recentSearch->id,
                'deduplicated' => true,
            ]);
        }

        $search = $this->analyticsService->trackSearch([
            'search_query'     => $request->search_query,
            'customer_id'      => $customer?->id,
            'session_id'       => $request->session_id,
            'has_results'      => $request->results_count > 0,
            'results_count'    => $request->results_count,
            'filters_applied'  => $request->filters_applied,
            'parent_search_id' => $request->parent_search_id,
            'searched_at'      => now(),
        ]);

        return ApiResponse::success('Search query tracked successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/central/marketplace/analytics/event",
     *     summary="Track generic analytics event",
     *     description="Records a generic analytics event with flexible properties. Supports various event types including page views, interactions, and custom events. Can be associated with products, categories, and includes page context. No authentication required for public tracking.",
     *     operationId="trackEvent",
     *     tags={"Central - Analytics - Marketplace"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Event tracking data",
     *         @OA\JsonContent(
     *             required={"event_type", "session_id"},
     *             @OA\Property(
     *                 property="event_type",
     *                 type="string",
     *                 description="Type of event being tracked (from TrackEvent enum)",
     *                 example="page_view"
     *             ),
     *             @OA\Property(
     *                 property="session_id",
     *                 type="string",
     *                 format="uuid",
     *                 description="Unique session identifier",
     *                 example="550e8400-e29b-41d4-a716-446655440000"
     *             ),
     *             @OA\Property(
     *                 property="marketplace_product_id",
     *                 type="integer",
     *                 description="Associated product ID if event is product-related",
     *                 example=15,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="marketplace_category_id",
     *                 type="integer",
     *                 description="Associated category ID if event is category-related",
     *                 example=5,
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="event_properties",
     *                 type="object",
     *                 description="Additional event-specific properties as key-value pairs",
     *                 example={"page_type": "product_detail"},
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="page_url",
     *                 type="string",
     *                 format="uri",
     *                 description="URL where the event occurred",
     *                 example="https://yourstore.com/products/15",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="referrer_url",
     *                 type="string",
     *                 format="uri",
     *                 description="URL of the referring page",
     *                 example="https://yourstore.com/search?q=running+shoes",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="time_on_page_seconds",
     *                 type="integer",
     *                 description="Time spent on the page in seconds",
     *                 example=35,
     *                 minimum=0,
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Event tracked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Event tracked successfully"),
     *             @OA\Property(
     *                 property="meta",
     *                 type="object",
     *                 @OA\Property(property="timestamp", type="string", format="date-time", example="2026-02-21T15:56:13.409052Z"),
     *                 @OA\Property(property="request_id", type="string", format="uuid", example="4c7b93d3-d6f0-4b26-8d5e-18b2e15aa443"),
     *                 @OA\Property(property="tenant_id", type="string", nullable=true, example=null),
     *                 @OA\Property(property="tenant_name", type="string", nullable=true, example=null)
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
    public function trackEvent(TrackEventRequest $request): JsonResponse
    {
        $customer  = CustomerHelper::getAuthenticatedCustomer();
        $eventType = TrackEvent::from($request->event_type);

        if ($eventType->shouldDeduplicate()) {
            $exists = CustomerJourneyEvent::on('central')
                ->where('session_uuid', $request->session_id)
                ->where('event_type', $eventType->value)
                ->where('marketplace_product_id', $request->marketplace_product_id)
                ->where('event_timestamp', '>', now()->subSeconds(10))
                ->exists();

            if ($exists) {
                return ApiResponse::success('Event already tracked recently', [
                    'deduplicated' => true,
                ]);
            }
        }

        $event = $this->analyticsService->trackEvent([
            'event_type'              => $eventType->value,
            'customer_id'             => $customer?->id,
            'session_id'              => $request->session_id,
            'marketplace_product_id'  => $request->marketplace_product_id,
            'marketplace_category_id' => $request->marketplace_category_id,
            'tenant_id'               => $request->tenant_id,
            'event_properties'        => $request->event_properties,
            'page_url'                => $request->page_url,
            'referrer_url'            => $request->referrer_url,
            'time_on_page_seconds'    => $request->time_on_page_seconds,
            'ip_address'              => $request->ip(),
            'user_agent'              => $request->userAgent(),
        ]);

        return ApiResponse::success('Event tracked successfully');
    }
}
