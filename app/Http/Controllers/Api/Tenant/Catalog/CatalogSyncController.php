<?php

namespace App\Http\Controllers\Api\Tenant\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Catalog\CatalogSyncRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Tenant\Catalog\CatalogDeltaSyncService;
use Illuminate\Http\JsonResponse;

class CatalogSyncController extends Controller
{
    public function __construct(
        private readonly CatalogDeltaSyncService $syncService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/tenant/catalog/sync",
     *     summary="Sync offline catalog data",
     *     description="Returns a full catalog snapshot when updated_since is omitted, or only rows changed after the supplied high-water cursor. The response is bounded to records changed at or before sync_started_at; clients should persist next_cursor and send it as updated_since on the next sync. Use include_deleted=true to receive soft-delete tombstones for supported entities.",
     *     operationId="syncTenantCatalog",
     *     tags={"Tenant Catalog"},
     *     security={{"sanctum": {}}},
     *
     *     @OA\Parameter(
     *         name="updated_since",
     *         in="query",
     *         required=false,
     *         description="ISO-8601 cursor from a previous response's next_cursor. Omit for a full snapshot.",
     *
     *         @OA\Schema(type="string", format="date-time"),
     *         example="2026-08-12T10:20:30.000000Z"
     *     ),
     *
     *     @OA\Parameter(
     *         name="include_deleted",
     *         in="query",
     *         required=false,
     *         description="When true, includes soft-deleted rows as tombstones where the underlying entity supports soft deletes.",
     *
     *         @OA\Schema(type="boolean", default=false),
     *         example=true
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Catalog sync retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Catalog sync retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="sync_started_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                 @OA\Property(property="updated_since", type="string", format="date-time", nullable=true, example="2026-08-11T10:20:30.000000Z"),
     *                 @OA\Property(property="next_cursor", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                 @OA\Property(property="include_deleted", type="boolean", example=false),
     *                 @OA\Property(property="entity_counts", type="object",
     *                     @OA\Property(property="categories", type="integer", example=12),
     *                     @OA\Property(property="brands", type="integer", example=6),
     *                     @OA\Property(property="products", type="integer", example=120),
     *                     @OA\Property(property="variants", type="integer", example=30),
     *                     @OA\Property(property="prices", type="integer", example=140),
     *                     @OA\Property(property="product_uoms", type="integer", example=120),
     *                     @OA\Property(property="uoms", type="integer", example=8),
     *                     @OA\Property(property="tax_rates", type="integer", example=3),
     *                     @OA\Property(property="customers", type="integer", example=25),
     *                     @OA\Property(property="promotions", type="integer", example=2),
     *                     @OA\Property(property="coupons", type="integer", example=4)
     *                 ),
     *                 @OA\Property(property="entities", type="object",
     *                     @OA\Property(property="categories", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="deleted", type="boolean", example=false),
     *                         @OA\Property(property="synced_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2026-08-01T10:20:30.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="name", type="string", example="General"),
     *                         @OA\Property(property="slug", type="string", example="general"),
     *                         @OA\Property(property="description", type="string", nullable=true, example=null),
     *                         @OA\Property(property="parent_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="display_order", type="integer", example=0),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="brands", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="deleted", type="boolean", example=false),
     *                         @OA\Property(property="synced_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true, example=null),
     *                         @OA\Property(property="name", type="string", example="Acme"),
     *                         @OA\Property(property="slug", type="string", example="acme"),
     *                         @OA\Property(property="logo_url", type="string", nullable=true, example=null),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_featured", type="boolean", example=false),
     *                         @OA\Property(property="display_order", type="integer", example=0)
     *                     )),
     *                     @OA\Property(property="products", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="uuid", type="string", format="uuid", nullable=true, example="67b466f5-8b6d-4122-af5d-1683d1dd7a72"),
     *                         @OA\Property(property="deleted", type="boolean", example=false),
     *                         @OA\Property(property="synced_at", type="string", format="date-time", example="2026-08-12T10:20:30.000000Z"),
     *                         @OA\Property(property="name", type="string", example="Product One"),
     *                         @OA\Property(property="slug", type="string", example="product-one"),
     *                         @OA\Property(property="sku", type="string", example="SKU-001"),
     *                         @OA\Property(property="category_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="brand_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="supplier_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="product_type", type="string", example="simple"),
     *                         @OA\Property(property="stock_status", type="string", example="in_stock"),
     *                         @OA\Property(property="base_selling_price", type="number", format="float", example=100.00),
     *                         @OA\Property(property="online_price", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="tax_rate_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="base_uom_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="is_weighed", type="boolean", example=false),
     *                         @OA\Property(property="requires_batch_tracking", type="boolean", example=false),
     *                         @OA\Property(property="requires_serial_tracking", type="boolean", example=false),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="variants", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=20),
     *                         @OA\Property(property="uuid", type="string", format="uuid", nullable=true, example=null),
     *                         @OA\Property(property="product_id", type="integer", example=10),
     *                         @OA\Property(property="variant_name", type="string", example="Small"),
     *                         @OA\Property(property="sku", type="string", nullable=true, example="SKU-001-S"),
     *                         @OA\Property(property="attributes", type="object"),
     *                         @OA\Property(property="uom_id", type="integer", nullable=true, example=1),
     *                         @OA\Property(property="uom_quantity", type="number", format="float", example=1),
     *                         @OA\Property(property="quantity_in_base_uom", type="number", format="float", example=1),
     *                         @OA\Property(property="variant_price", type="number", format="float", nullable=true, example=null),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="prices", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=30),
     *                         @OA\Property(property="store_id", type="integer", example=1),
     *                         @OA\Property(property="product_id", type="integer", example=10),
     *                         @OA\Property(property="product_variant_id", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="store_selling_price", type="number", format="float", nullable=true, example=95.00),
     *                         @OA\Property(property="is_available", type="boolean", example=true),
     *                         @OA\Property(property="min_stock_level", type="integer", example=0)
     *                     )),
     *                     @OA\Property(property="product_uoms", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=40),
     *                         @OA\Property(property="product_id", type="integer", example=10),
     *                         @OA\Property(property="uom_id", type="integer", example=1),
     *                         @OA\Property(property="is_base_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_purchase_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_sales_uom", type="boolean", example=true),
     *                         @OA\Property(property="is_inventory_uom", type="boolean", example=true),
     *                         @OA\Property(property="conversion_to_base", type="number", format="float", example=1)
     *                     )),
     *                     @OA\Property(property="uoms", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="code", type="string", example="pcs"),
     *                         @OA\Property(property="name", type="string", example="Piece"),
     *                         @OA\Property(property="type", type="string", example="count"),
     *                         @OA\Property(property="source_type", type="string", example="system"),
     *                         @OA\Property(property="is_base_unit", type="boolean", example=true),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="tax_rates", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="tax_name", type="string", example="VAT"),
     *                         @OA\Property(property="rate", type="number", format="float", example=16),
     *                         @OA\Property(property="effective_from", type="string", format="date", example="2026-01-01"),
     *                         @OA\Property(property="effective_until", type="string", format="date", nullable=true, example=null),
     *                         @OA\Property(property="is_active", type="boolean", example=true),
     *                         @OA\Property(property="is_default", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="customers", type="array", @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="customer_number", type="string", example="CUS-001"),
     *                         @OA\Property(property="name", type="string", example="Jane Buyer"),
     *                         @OA\Property(property="email", type="string", format="email", nullable=true, example="jane@example.com"),
     *                         @OA\Property(property="phone", type="string", nullable=true, example="+254700000000"),
     *                         @OA\Property(property="customer_type", type="string", example="regular"),
     *                         @OA\Property(property="loyalty_points", type="number", format="float", example=25),
     *                         @OA\Property(property="credit_limit", type="number", format="float", example=0),
     *                         @OA\Property(property="current_debt", type="number", format="float", example=0),
     *                         @OA\Property(property="store_credit_balance", type="number", format="float", example=0),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     )),
     *                     @OA\Property(property="promotions", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="coupons", type="array", @OA\Items(type="object"))
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error - invalid updated_since date or include_deleted value")
     * )
     */
    public function __invoke(CatalogSyncRequest $request): JsonResponse
    {
        return ApiResponse::success(
            'Catalog sync retrieved successfully',
            $this->syncService->sync(
                updatedSince: $request->updatedSince(),
                includeDeleted: $request->includeDeleted(),
            )
        );
    }
}
