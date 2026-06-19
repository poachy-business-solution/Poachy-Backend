<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private SkuGeneratorService $skuGenerator
    ) {}

    // Create a product
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            // Generate UUID
            $data['uuid'] = Str::uuid()->toString();

            // Generate slug 
            $data['slug'] = $this->generateUniqueSlug($data['name']);

            // Generate SKU if not provided
            if (empty($data['sku'])) {
                $data['sku'] = $this->skuGenerator->generate($data);
            }

            // Validate SKU uniqueness
            if (Product::where('sku', $data['sku'])->exists()) {
                throw new \InvalidArgumentException('SKU already exists');
            }

            // Handle primary image
            if (isset($data['primary_image']) && $data['primary_image'] instanceof UploadedFile) {
                $data['primary_image'] = $this->uploadPrimaryImage($data['primary_image']);
            }

            // Handle secondary images
            if (isset($data['secondary_images']) && is_array($data['secondary_images'])) {
                $data['secondary_images'] = $this->uploadSecondaryImages($data['secondary_images']);
            } else {
                $data['secondary_images'] = [];
            }

            $product = Product::create($data);

            // Clear product cache
            $this->clearProductCache();

            return $product->load(['category', 'brand']);
        });
    }

    /**
     * Update product core details
     */
    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            // Regenerate slug if name changed
            if (isset($data['name']) && $data['name'] !== $product->name) {
                if (empty($data['slug'])) {
                    $data['slug'] = $this->generateUniqueSlug($data['name'], $product->id);
                }
            }

            // Validate SKU uniqueness if changed
            if (isset($data['sku']) && $data['sku'] !== $product->sku) {
                if (Product::where('sku', $data['sku'])->where('id', '!=', $product->id)->exists()) {
                    throw new \InvalidArgumentException('SKU already exists');
                }
            }

            $product->update($data);

            // Clear cache
            $this->clearProductCache($product->uuid);

            Log::info('Product updated', [
                'tenant_id' => tenant()->id,
                'product_id' => $product->id,
                'product_uuid' => $product->uuid,
                'updated_fields' => array_keys($data),
            ]);

            return $product->fresh(['category', 'brand', 'supplier']);
        });
    }

    /**
     * Update inventory & logistics configuration
     */
    public function updateInventoryConfig(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($data);

            $this->clearProductCache($product->uuid);

            Log::info('Product inventory config updated', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
            ]);

            return $product->fresh();
        });
    }

    /**
     * Update online marketplace configuration
     */
    public function updateOnlineConfig(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update($data);

            $this->clearProductCache($product->uuid);

            Log::info('Product online config updated', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'is_available_online' => $data['is_available_online'] ?? $product->is_available_online,
            ]);

            // TODO: Trigger sync to marketplace if is_available_online = true

            return $product->fresh();
        });
    }

    /**
     * Toggle product active status
     */
    public function toggleActive(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $newStatus = !$product->is_active;
            $product->update(['is_active' => $newStatus]);

            $this->clearProductCache($product->uuid);

            Log::info('Product active status toggled', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'is_active' => $newStatus,
            ]);

            return $product;
        });
    }

    /**
     * Toggle product featured status
     */
    public function toggleFeatured(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $newStatus = !$product->is_featured;
            $product->update(['is_featured' => $newStatus]);

            $this->clearProductCache($product->uuid);

            Log::info('Product featured status toggled', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'is_featured' => $newStatus,
            ]);

            return $product;
        });
    }

    /**
     * Add product images
     */
    public function addImages(Product $product, array $images): Product
    {
        return DB::transaction(function () use ($product, $images) {
            // Upload the new images and get their paths
            $uploadedImagePaths = $this->uploadSecondaryImages($images);

            // Get existing images and merge with new ones
            $existingImages = $product->secondary_images ?? [];
            $newImages = array_merge($existingImages, $uploadedImagePaths);

            // Update product with new images
            $product->update(['secondary_images' => $newImages]);

            // Clear cache
            $this->clearProductCache($product->uuid);

            Log::info('Product images added', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'images_added' => count($uploadedImagePaths),
                'total_images' => count($newImages),
            ]);

            return $product->fresh();
        });
    }

    /**
     * Remove a specific product image
     */
    public function deleteImages(Product $product, array $imagePaths): array
    {
        return DB::transaction(function () use ($product, $imagePaths) {
            $existingImages = $product->secondary_images ?? [];
            $deletedCount = 0;
            $failedCount = 0;
            $failedImages = [];

            // Validate that requested images exist in product's secondary images
            $imagesToDelete = array_intersect($existingImages, $imagePaths);

            if (empty($imagesToDelete)) {
                Log::warning('No matching images found to delete', [
                    'tenant_id' => tenant()->id,
                    'product_uuid' => $product->uuid,
                    'requested_images' => $imagePaths,
                    'existing_images' => $existingImages,
                ]);

                return [
                    'deleted_count' => 0,
                    'failed_count' => count($imagePaths),
                    'remaining_images' => count($existingImages),
                    'failed_images' => $imagePaths,
                ];
            }

            // Delete each image from storage
            foreach ($imagesToDelete as $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    try {
                        Storage::disk('public')->delete($imagePath);
                        $deletedCount++;

                        Log::info('Secondary image deleted', [
                            'tenant_id' => tenant()->id,
                            'product_uuid' => $product->uuid,
                            'image_path' => $imagePath,
                        ]);
                    } catch (\Exception $e) {
                        $failedCount++;
                        $failedImages[] = $imagePath;

                        Log::error('Failed to delete secondary image', [
                            'tenant_id' => tenant()->id,
                            'product_uuid' => $product->uuid,
                            'image_path' => $imagePath,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    // Image doesn't exist in storage, but we'll still remove from DB
                    $deletedCount++;

                    Log::warning('Image not found in storage, removing from database', [
                        'tenant_id' => tenant()->id,
                        'product_uuid' => $product->uuid,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            // Update product by removing deleted images
            $remainingImages = array_values(array_diff($existingImages, $imagesToDelete));
            $product->update(['secondary_images' => $remainingImages]);

            // Clear cache
            $this->clearProductCache($product->uuid);

            Log::info('Product images deletion completed', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'remaining_images' => count($remainingImages),
            ]);

            return [
                'deleted_count' => $deletedCount,
                'failed_count' => $failedCount,
                'remaining_images' => count($remainingImages),
                'failed_images' => $failedImages,
            ];
        });
    }

    /**
     * Replace primary image (delete old, upload new)
     */
    public function replacePrimaryImage(Product $product, UploadedFile $newImage): string
    {
        // Delete old primary image if exists
        if ($product->primary_image && Storage::disk('public')->exists($product->primary_image)) {
            Storage::disk('public')->delete($product->primary_image);

            Log::info('Old primary image deleted during replacement', [
                'tenant_id' => tenant()->id,
                'product_uuid' => $product->uuid,
                'old_image_path' => $product->primary_image,
            ]);
        }

        // Upload and return new image path
        return $this->uploadPrimaryImage($newImage);
    }

    /**
     * Get paginated products with filters and search
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Product::query()
            ->with(['category:id,name,slug', 'brand:id,name,slug'])
            ->select([
                'id',
                'uuid',
                'name',
                'slug',
                'sku',
                'category_id',
                'brand_id',
                'base_selling_price',
                'online_price',
                'stock_status',
                'is_active',
                'is_featured',
                'is_available_online',
                'primary_image',
                'created_at',
            ]);

        // Search
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Brand filter
        if (!empty($filters['brand_id'])) {
            $query->where('brand_id', $filters['brand_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('stock_status', $filters['status']);
        }

        // Active filter
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Featured filter
        if (isset($filters['is_featured'])) {
            $query->where('is_featured', (bool) $filters['is_featured']);
        }

        // Online filter
        if (isset($filters['is_available_online'])) {
            $query->where('is_available_online', (bool) $filters['is_available_online']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get single product with full details
     */
    public function getByUuid(string $uuid): Product
    {
        $cacheKey = $this->getProductCacheKey($uuid);

        return Cache::tags(['tenant', tenant()->id, 'products'])->remember(
            $cacheKey,
            now()->addHours(6),
            function () use ($uuid) {
                return Product::with([
                    'category',
                    'brand',
                    'supplier',
                    'taxRate',
                    'baseUom',
                ])
                    ->where('uuid', $uuid)
                    ->firstOrFail();
            }
        );
    }

    /**
     * Generate unique slug
     */
    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = Product::where('slug', $slug);

            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }

            if (!$query->exists()) {
                return $slug;
            }

            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }

    // Upload primary product image
    protected function uploadPrimaryImage(UploadedFile $file): string
    {
        // Get original filename without extension
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Create filename with timestamp
        $filename = 'primary_' . Str::slug($originalName) . '_' . time() . '.' . $extension;

        // Store in public disk under products/images
        $path = $file->storeAs('products/images', $filename, 'public');

        return $path;
    }

    // Upload secondary product images
    protected function uploadSecondaryImages(array $files): array
    {
        $paths = [];

        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                // Get original filename without extension
                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();

                // Create filename with timestamp and index
                $filename = 'secondary_' . Str::slug($originalName) . '_' . time() . '_' . $index . '.' . $extension;

                // Store in public disk under products/images
                $path = $file->storeAs('products/images', $filename, 'public');

                $paths[] = $path;
            }
        }

        return $paths;
    }

    // Delete product images
    protected function deleteProductImages(Product $product): void
    {
        // Delete primary image
        if ($product->primary_image && Storage::disk('public')->exists($product->primary_image)) {
            Storage::disk('public')->delete($product->primary_image);
        }

        // Delete secondary images
        if (!empty($product->secondary_images)) {
            foreach ($product->secondary_images as $imagePath) {
                if (Storage::disk('public')->exists($imagePath)) {
                    Storage::disk('public')->delete($imagePath);
                }
            }
        }
    }

    /**
     * Clear product cache
     */
    public function clearProductCache(?string $uuid = null): void
    {
        $tags = ['tenant', tenant()->id, 'products'];

        if ($uuid) {
            Cache::tags($tags)->forget($this->getProductCacheKey($uuid));
        } else {
            Cache::tags($tags)->flush();
        }
    }

    /**
     * Get product cache key
     */
    private function getProductCacheKey(string $uuid): string
    {
        return "product:{$uuid}";
    }
}
