<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\ProductBundle;
use App\Models\Tenant\ProductBundleItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductBundleService
{
    public function __construct(
        private SkuGeneratorService $skuGenerator
    ) {}

    /**
     * List bundles with filters and pagination
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductBundle::query()
            ->with(['baseUom', 'taxRate', 'items.product', 'items.variant', 'items.uom']);

        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Online filter
        if (isset($filters['is_online'])) {
            $query->where('is_available_online', (bool) $filters['is_online']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get bundle by ID
     */
    public function getById(int $id): ProductBundle
    {
        return ProductBundle::with(['baseUom', 'taxRate', 'items.product', 'items.variant', 'items.uom'])
            ->findOrFail($id);
    }

    /**
     * Create new bundle
     */
    public function create(array $data): ProductBundle
    {
        return DB::transaction(function () use ($data) {
            // Generate SKU if not provided
            if (empty($data['bundle_sku'])) {
                $data['bundle_sku'] = $this->skuGenerator->generateBundleSku(
                    $data['category_id'] ?? null,
                    $data['bundle_name'] ?? null
                );
            }

            // Validate SKU uniqueness
            if (ProductBundle::where('bundle_sku', $data['bundle_sku'])->exists()) {
                throw new \InvalidArgumentException('Bundle SKU already exists');
            }

            // Extract items data
            $items = $data['items'] ?? [];
            unset($data['items'], $data['category_id']);

            // Create bundle
            $bundle = ProductBundle::create($data);

            // Add items if provided
            if (!empty($items)) {
                foreach ($items as $itemData) {
                    $this->addItem($bundle, $itemData);
                }

                // Recalculate pricing
                $bundle->recalculatePricing();
            }

            Log::info('Product bundle created', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'bundle_sku' => $bundle->bundle_sku,
                'items_count' => count($items),
            ]);

            return $bundle->fresh(['baseUom', 'taxRate', 'items.product', 'items.variant', 'items.uom']);
        });
    }

    /**
     * Update bundle
     */
    public function update(ProductBundle $bundle, array $data): ProductBundle
    {
        return DB::transaction(function () use ($bundle, $data) {
            // Validate SKU if changed
            if (isset($data['bundle_sku']) && $data['bundle_sku'] !== $bundle->bundle_sku) {
                if (ProductBundle::where('bundle_sku', $data['bundle_sku'])->where('id', '!=', $bundle->id)->exists()) {
                    throw new \InvalidArgumentException('Bundle SKU already exists');
                }
            }

            $bundle->update($data);

            Log::info('Product bundle updated', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'changes' => $bundle->getChanges(),
            ]);

            return $bundle->fresh(['baseUom', 'taxRate', 'items.product', 'items.variant', 'items.uom']);
        });
    }

    /**
     * Delete bundle
     */
    public function delete(ProductBundle $bundle): bool
    {
        return DB::transaction(function () use ($bundle) {
            $bundleSku = $bundle->bundle_sku;
            $bundle->delete();

            Log::warning('Product bundle deleted', [
                'tenant_id' => tenant()->id,
                'bundle_sku' => $bundleSku,
            ]);

            return true;
        });
    }

    /**
     * Add item to bundle
     */
    public function addItem(ProductBundle $bundle, array $data): ProductBundleItem
    {
        return DB::transaction(function () use ($bundle, $data) {
            // Calculate base UOM quantity if not provided
            if (!isset($data['quantity_in_base_uom'])) {
                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity($data);
            }

            $data['bundle_id'] = $bundle->id;
            $item = ProductBundleItem::create($data);

            // Recalculate bundle pricing
            $bundle->recalculatePricing();

            Log::info('Bundle item added', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->product_variant_id,
            ]);

            return $item->fresh(['product', 'variant', 'uom']);
        });
    }

    /**
     * Update bundle item
     */
    public function updateItem(ProductBundleItem $item, array $data): ProductBundleItem
    {
        return DB::transaction(function () use ($item, $data) {
            // Recalculate base UOM if quantity or UOM changed
            if ((isset($data['quantity']) || isset($data['uom_id'])) && !isset($data['quantity_in_base_uom'])) {
                $data['quantity_in_base_uom'] = $this->calculateBaseUomQuantity(array_merge([
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'uom_id' => $data['uom_id'] ?? $item->uom_id,
                    'quantity' => $data['quantity'] ?? $item->quantity,
                ]));
            }

            $item->update($data);

            // Recalculate bundle pricing
            $item->bundle->recalculatePricing();

            Log::info('Bundle item updated', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $item->bundle_id,
                'item_id' => $item->id,
                'changes' => $item->getChanges(),
            ]);

            return $item->fresh(['product', 'variant', 'uom']);
        });
    }

    /**
     * Remove item from bundle
     */
    public function removeItem(ProductBundleItem $item): bool
    {
        return DB::transaction(function () use ($item) {
            $bundle = $item->bundle;

            // Check minimum items
            // if ($bundle->items()->count() <= 2) {
            //     throw new \InvalidArgumentException('Bundle must have at least 2 items');
            // }

            $item->delete();

            // Recalculate bundle pricing
            $bundle->recalculatePricing();

            Log::info('Bundle item removed', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'product_id' => $item->product_id,
            ]);

            return true;
        });
    }

    /**
     * Toggle bundle active status
     */
    public function toggleActive(ProductBundle $bundle): ProductBundle
    {
        return DB::transaction(function () use ($bundle) {
            $newStatus = !$bundle->is_active;
            $bundle->update(['is_active' => $newStatus]);

            Log::info('Bundle active status toggled', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'is_active' => $newStatus,
            ]);

            return $bundle->fresh();
        });
    }

    /**
     * Toggle bundle online availability
     */
    public function toggleOnline(ProductBundle $bundle): ProductBundle
    {
        return DB::transaction(function () use ($bundle) {
            $newStatus = !$bundle->is_available_online;
            $bundle->update(['is_available_online' => $newStatus]);

            Log::info('Bundle online status toggled', [
                'tenant_id' => tenant()->id,
                'bundle_id' => $bundle->id,
                'is_available_online' => $newStatus,
            ]);

            return $bundle->fresh();
        });
    }

    /**
     * Update bundle pricing
     */
    public function updatePricing(ProductBundle $bundle, array $data): ProductBundle
    {
        return DB::transaction(function () use ($bundle, $data) {
            $bundle->update($data);
            $bundle->recalculatePricing();

            return $bundle->fresh();
        });
    }

    /**
     * Add images to bundle
     */
    public function addImages(ProductBundle $bundle, array $imageFiles): ProductBundle
    {
        $currentImages = $bundle->images ?? [];

        // Upload new images
        $newImagePaths = $this->uploadBundleImages($imageFiles, $bundle->id);

        // Merge with existing images
        $allImages = array_merge($currentImages, $newImagePaths);

        // Limit to 10 images total
        $allImages = array_slice($allImages, 0, 10);

        $bundle->update(['images' => $allImages]);

        return $bundle->fresh();
    }


    /**
     * Remove image from bundle
     */
    public function removeImage(ProductBundle $bundle, string $imagePath): ProductBundle
    {
        $images = $bundle->images ?? [];

        // Check if image exists in bundle's images array
        $imageKey = array_search($imagePath, $images);
        if ($imageKey === false) {
            throw new \InvalidArgumentException('Image not found in this bundle');
        }

        // Attempt to delete the physical file from storage
        try {
            if (Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
                Log::info("Deleted bundle image: {$imagePath}");
            } else {
                Log::warning("Image file not found in storage: {$imagePath}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to delete bundle image: {$imagePath}", ['error' => $e->getMessage()]);
        }

        // Remove from array
        unset($images[$imageKey]);

        // Update bundle with re-indexed array
        $bundle->update(['images' => array_values($images)]);

        return $bundle->fresh();
    }

    protected function uploadBundleImages(array $files, int $bundleId): array
    {
        $paths = [];

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                // Get original filename without extension
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                // Create filename with bundle ID, timestamp and index
                $filename = 'bundle_' . $bundleId . '_' . Str::slug($originalName) . '_' . time() . '_' . $index . '.' . $extension;

                // Store in public disk under bundles/images
                $path = $file->storeAs('bundles/images', $filename, 'public');

                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * Calculate base UOM quantity
     */
    private function calculateBaseUomQuantity(array $data): float
    {
        $productId = $data['product_id'];
        $variantId = $data['product_variant_id'] ?? null;
        $uomId = $data['uom_id'];
        $quantity = $data['quantity'];

        // If using variant, use variant's conversion
        if ($variantId) {
            $variant = \App\Models\Tenant\ProductVariant::findOrFail($variantId);
            return $variant->convertToBaseUom($quantity);
        }

        // Otherwise use product UOM conversion
        $product = \App\Models\Tenant\Product::findOrFail($productId);
        $productUom = $product->productUoms()->where('uom_id', $uomId)->first();

        if (!$productUom) {
            throw new \InvalidArgumentException('UOM is not configured for this product');
        }

        return $productUom->convertToBase($quantity);
    }

    /**
     * Check stock availability for bundle
     */
    public function checkStockAvailability(ProductBundle $bundle, int $requestedQuantity = 1): array
    {
        $availability = [];
        $allAvailable = true;

        foreach ($bundle->items as $item) {
            $requiredQuantity = $item->quantity_in_base_uom * $requestedQuantity;

            // Get current stock (simplified - would query inventory table in production)
            $available = true; // TODO: Check actual inventory

            $availability[] = [
                'item_id' => $item->id,
                'product_name' => $item->display_name,
                'required_quantity' => $requiredQuantity,
                'available' => $available,
            ];

            if (!$available) {
                $allAvailable = false;
            }
        }

        return [
            'available' => $allAvailable,
            'items' => $availability,
        ];
    }

    /**
     * Get bundle breakdown with prices
     */
    public function getBreakdown(ProductBundle $bundle): array
    {
        $items = [];
        $individualTotal = 0;

        foreach ($bundle->items as $item) {
            $itemPrice = $item->item_price;
            $itemTotal = $item->total_price;
            $individualTotal += $itemTotal;

            $items[] = [
                'id' => $item->id,
                'name' => $item->display_name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'uom' => $item->uom?->code,
                'unit_price' => $itemPrice,
                'total_price' => $itemTotal,
                'formatted_unit_price' => 'KES ' . number_format($itemPrice, 2),
                'formatted_total_price' => 'KES ' . number_format($itemTotal, 2),
            ];
        }

        $savings = max(0, $individualTotal - $bundle->bundle_price);
        $savingsPercent = $individualTotal > 0 ? round(($savings / $individualTotal) * 100, 2) : 0;

        return [
            'items' => $items,
            'individual_total' => $individualTotal,
            'bundle_price' => $bundle->bundle_price,
            'savings' => $savings,
            'savings_percentage' => $savingsPercent,
            'formatted_individual_total' => 'KES ' . number_format($individualTotal, 2),
            'formatted_bundle_price' => 'KES ' . number_format($bundle->bundle_price, 2),
            'formatted_savings' => 'KES ' . number_format($savings, 2),
        ];
    }
}
