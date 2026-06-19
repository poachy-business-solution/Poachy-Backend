<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Promotion;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromotionRepository
{
    /**
     * Get paginated promotions with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Promotion::query()
            ->with(['products', 'categories', 'brands'])
            ->withCount(['products', 'categories', 'brands']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['promotion_type'])) {
            $query->filterByType($filters['promotion_type']);
        }

        if (!empty($filters['applicable_to'])) {
            $query->filterByApplicability($filters['applicable_to']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['store_id'])) {
            $query->applicableToStore($filters['store_id']);
        }

        if (!empty($filters['show_in_pos'])) {
            $query->showInPos();
        }

        if (!empty($filters['show_on_website'])) {
            $query->showOnWebsite();
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find promotion by ID with relationships
     */
    public function findById(int $id): ?Promotion
    {
        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember("promotion:{$id}", 3600, function () use ($id) {
                return Promotion::with(['products', 'categories', 'brands'])
                    ->withCount(['products', 'categories', 'brands'])
                    ->find($id);
            });
    }

    /**
     * Find promotion by code
     */
    public function findByCode(string $code): ?Promotion
    {
        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember("promotion_code:{$code}", 3600, function () use ($code) {
                return Promotion::byCode($code)->first();
            });
    }

    /**
     * Get currently running promotions
     */
    public function getCurrentlyRunning(?int $storeId = null, ?Carbon $now = null): Collection
    {
        $cacheKey = $storeId
            ? "running_promotions:store:{$storeId}"
            : 'running_promotions:all';

        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember($cacheKey, 900, function () use ($storeId, $now) {
                $query = Promotion::currentlyRunning($now)
                    ->with(['products', 'categories', 'brands']);

                if ($storeId) {
                    $query->applicableToStore($storeId);
                }

                return $query->orderByDesc('display_priority')
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
    }

    /**
     * Get featured promotions (for banners)
     */
    public function getFeatured(?int $storeId = null): Collection
    {
        $cacheKey = $storeId
            ? "featured_promotions:store:{$storeId}"
            : 'featured_promotions:all';

        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember($cacheKey, 1800, function () use ($storeId) {
                $query = Promotion::available()
                    ->featured();

                if ($storeId) {
                    $query->applicableToStore($storeId);
                }

                return $query->take(5)->get();
            });
    }

    /**
     * Get POS-visible promotions
     */
    public function getPosPromotions(?int $storeId = null): Collection
    {
        $cacheKey = $storeId
            ? "pos_promotions:store:{$storeId}"
            : 'pos_promotions:all';

        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember($cacheKey, 1800, function () use ($storeId) {
                $query = Promotion::available()
                    ->showInPos();

                if ($storeId) {
                    $query->applicableToStore($storeId);
                }

                return $query->orderByDesc('display_priority')
                    ->get();
            });
    }

    /**
     * Get website-visible promotions
     */
    public function getWebsitePromotions(?int $storeId = null): Collection
    {
        $cacheKey = $storeId
            ? "website_promotions:store:{$storeId}"
            : 'website_promotions:all';

        return Cache::tags(['tenant', tenant()->id, 'promotions'])
            ->remember($cacheKey, 1800, function () use ($storeId) {
                $query = Promotion::available()
                    ->showOnWebsite();

                if ($storeId) {
                    $query->applicableToStore($storeId);
                }

                return $query->orderByDesc('display_priority')
                    ->get();
            });
    }

    /**
     * Create new promotion
     */
    public function create(array $data): Promotion
    {
        return DB::transaction(function () use ($data) {
            $promotion = Promotion::create($data);

            $this->invalidateCache();

            return $promotion->load(['products', 'categories', 'brands']);
        });
    }

    /**
     * Update promotion
     */
    public function update(Promotion $promotion, array $data): Promotion
    {
        DB::transaction(function () use ($promotion, $data) {
            $promotion->update($data);

            $this->invalidateCache($promotion->id);
        });

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Delete promotion
     */
    public function delete(Promotion $promotion): bool
    {
        return DB::transaction(function () use ($promotion) {
            $deleted = $promotion->delete();

            if ($deleted) {
                $this->invalidateCache($promotion->id);
            }

            return $deleted;
        });
    }

    /**
     * Activate promotion
     */
    public function activate(Promotion $promotion): Promotion
    {
        DB::transaction(function () use ($promotion) {
            $promotion->update(['is_active' => true]);
            $this->invalidateCache($promotion->id);
        });

        return $promotion->fresh();
    }

    /**
     * Deactivate promotion
     */
    public function deactivate(Promotion $promotion): Promotion
    {
        DB::transaction(function () use ($promotion) {
            $promotion->update(['is_active' => false]);
            $this->invalidateCache($promotion->id);
        });

        return $promotion->fresh();
    }

    /**
     * Attach products to promotion
     */
    public function attachProducts(Promotion $promotion, array $productsData): void
    {
        DB::transaction(function () use ($promotion, $productsData) {
            foreach ($productsData as $productData) {
                $productId = $productData['product_id'];
                $variantId = $productData['product_variant_id'] ?? null;

                $exists = $promotion->products()
                    ->where('products.id', $productId)
                    ->wherePivot('product_variant_id', $variantId)
                    ->exists();

                if (!$exists) {
                    $promotion->products()->attach($productId, [
                        'product_variant_id' => $variantId
                    ]);
                }
            }

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Detach product from promotion
     */
    public function detachProduct(Promotion $promotion, int $productId): void
    {
        DB::transaction(function () use ($promotion, $productId) {
            $promotion->products()->detach($productId);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Attach categories to promotion
     */
    public function attachCategories(Promotion $promotion, array $categoryIds): void
    {
        DB::transaction(function () use ($promotion, $categoryIds) {
            $promotion->categories()->syncWithoutDetaching($categoryIds);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Detach category from promotion
     */
    public function detachCategory(Promotion $promotion, int $categoryId): void
    {
        DB::transaction(function () use ($promotion, $categoryId) {
            $promotion->categories()->detach($categoryId);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Attach brands to promotion
     */
    public function attachBrands(Promotion $promotion, array $brandIds): void
    {
        DB::transaction(function () use ($promotion, $brandIds) {
            $promotion->brands()->syncWithoutDetaching($brandIds);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Detach brand from promotion
     */
    public function detachBrand(Promotion $promotion, int $brandId): void
    {
        DB::transaction(function () use ($promotion, $brandId) {
            $promotion->brands()->detach($brandId);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Bulk attach products
     */
    public function bulkAttachProducts(Promotion $promotion, array $productsData): void
    {
        $this->attachProducts($promotion, $productsData);
    }

    /**
     * Bulk detach products
     */
    public function bulkDetachProducts(Promotion $promotion, array $productIds): void
    {
        DB::transaction(function () use ($promotion, $productIds) {
            $promotion->products()->detach($productIds);

            $this->invalidateCache($promotion->id);
        });
    }

    /**
     * Update applicable stores
     */
    public function updateApplicableStores(Promotion $promotion, ?array $storeIds): Promotion
    {
        DB::transaction(function () use ($promotion, $storeIds) {
            $promotion->update(['applicable_store_ids' => $storeIds]);
            $this->invalidateCache($promotion->id);
        });

        return $promotion->fresh();
    }

    /**
     * Update applicable customer groups
     */
    public function updateApplicableCustomerGroups(Promotion $promotion, ?array $customerGroupIds): Promotion
    {
        DB::transaction(function () use ($promotion, $customerGroupIds) {
            $promotion->update(['applicable_customer_group_ids' => $customerGroupIds]);
            $this->invalidateCache($promotion->id);
        });

        return $promotion->fresh();
    }

    /**
     * Check if code exists (tenant-scoped)
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = Promotion::byCode($code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Update promotion banner image
     */
    public function updateBanner(Promotion $promotion, UploadedFile $bannerImage): Promotion
    {
        return DB::transaction(function () use ($promotion, $bannerImage) {
            // Delete old banner if exists
            $this->deleteOldBanner($promotion);

            // Upload new banner
            $bannerPath = $this->uploadBannerImage($bannerImage);

            // Update promotion
            $promotion->update([
                'banner_image_url' => $bannerPath
            ]);

            $this->invalidateCache($promotion->id);

            return $promotion->fresh();
        });
    }

    /**
     * Delete old banner image from storage
     */
    protected function deleteOldBanner(Promotion $promotion): void
    {
        if ($promotion->banner_image_url && Storage::disk('public')->exists($promotion->banner_image_url)) {
            Storage::disk('public')->delete($promotion->banner_image_url);
        }
    }

    /**
     * Upload banner image to storage
     */
    protected function uploadBannerImage(UploadedFile $file): string
    {
        // Get original filename without extension
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Create filename with timestamp
        $filename = 'banner_' . Str::slug($originalName) . '_' . time() . '.' . $extension;

        // Store in public disk under promotions/banners
        $path = $file->storeAs('promotions/banners', $filename, 'public');

        return $path;
    }

    /**
     * Remove promotion banner
     */
    public function removeBanner(Promotion $promotion): Promotion
    {
        return DB::transaction(function () use ($promotion) {
            // Delete file from storage
            $this->deleteOldBanner($promotion);

            // Update promotion
            $promotion->update([
                'banner_image_url' => null
            ]);

            $this->invalidateCache($promotion->id);

            return $promotion->fresh();
        });
    }

    /**
     * Invalidate cache
     */
    protected function invalidateCache(?int $promotionId = null): void
    {
        Cache::tags(['tenant', tenant()->id, 'promotions'])->flush();

        if ($promotionId) {
            Cache::tags(['tenant', tenant()->id, 'promotions'])->forget("promotion:{$promotionId}");
        }
    }
}
