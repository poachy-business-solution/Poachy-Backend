<?php

namespace App\Http\Controllers\Api\Internal\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\Admin\MapMarketplaceProductRequest;
use App\Http\Requests\Internal\Admin\ReasonRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceProduct;
use App\Services\Central\Admin\AdminAudit;
use App\Services\Central\Marketplace\MarketplaceItemService;
use App\Services\Central\Marketplace\MarketplaceProductService;
use App\Support\ActingAdmin;
use Illuminate\Http\JsonResponse;

/** Admin: group listings into items when the barcode did not do it (or did it wrong). */
class MarketplaceMappingController extends Controller
{
    public function __construct(private readonly AdminAudit $audit, private readonly MarketplaceItemService $items) {}

    /**
     * @OA\Post(
     *     path="/api/internal/admin/marketplace/products/{id}/item",
     *     summary="Map a listing to an item (existing id or a new name)",
     *     operationId="internalAdminMarketplaceMap",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(@OA\JsonContent(
     *
     *         @OA\Property(property="item_id", type="integer", nullable=true),
     *         @OA\Property(property="item_name", type="string", nullable=true, example="Mango Juice 1L"),
     *         @OA\Property(property="reason", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="item{id,slug,name,offers_count}"),
     *     @OA\Response(response=404, description="No such listing")
     * )
     */
    public function map(MapMarketplaceProductRequest $request, int $id): JsonResponse
    {
        $product = MarketplaceProduct::on('central')->find($id);

        if (! $product) {
            return ApiResponse::notFound('Listing not found.');
        }

        $before = ['marketplace_item_id' => $product->marketplace_item_id];
        $target = $request->validated('item_id') ? MarketplaceItem::on('central')->findOrFail($request->validated('item_id')) : null;
        $item = $this->items->map($product, $target, $request->validated('item_name'), ActingAdmin::from($request)->id);
        app(MarketplaceProductService::class)->flushCache();

        $this->audit->record($request, 'marketplace.product.map', $product->tenant_id, 'marketplace_product', $product->id, $before, ['marketplace_item_id' => $item->id, 'item' => $item->name], $request->validated('reason'));

        return ApiResponse::success('Listing mapped', ['item' => ['id' => $item->id, 'slug' => $item->slug, 'name' => $item->name, 'offers_count' => $item->offers()->count()]]);
    }

    /**
     * @OA\Delete(
     *     path="/api/internal/admin/marketplace/products/{id}/item",
     *     summary="Take a listing out of its item",
     *     operationId="internalAdminMarketplaceUnmap",
     *     tags={"Internal admin - Marketplace"},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Unmapped"),
     *     @OA\Response(response=404, description="No such listing")
     * )
     */
    public function unmap(ReasonRequest $request, int $id): JsonResponse
    {
        $product = MarketplaceProduct::on('central')->find($id);

        if (! $product) {
            return ApiResponse::notFound('Listing not found.');
        }

        $before = ['marketplace_item_id' => $product->marketplace_item_id];
        $this->items->unmap($product);
        app(MarketplaceProductService::class)->flushCache();
        $this->audit->record($request, 'marketplace.product.unmap', $product->tenant_id, 'marketplace_product', $product->id, $before, ['marketplace_item_id' => null], $request->validated('reason'));

        return ApiResponse::success('Listing unmapped');
    }
}
