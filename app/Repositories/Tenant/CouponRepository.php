<?php

namespace App\Repositories\Tenant;

use App\Models\Tenant\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CouponRepository
{
    /**
     * Get paginated coupons with filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Coupon::query()
            ->with(['products', 'categories', 'brands'])
            ->withCount(['products', 'categories', 'brands']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            $query->filterByStatus($filters['status']);
        }

        if (!empty($filters['applicable_to'])) {
            $query->filterByApplicability($filters['applicable_to']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Find coupon by ID with relationships
     */
    public function findById(int $id): ?Coupon
    {
        return Cache::tags(['tenant', tenant()->id, 'coupons'])
            ->remember("coupon:{$id}", 3600, function () use ($id) {
                return Coupon::with(['products', 'categories', 'brands'])
                    ->withCount(['products', 'categories', 'brands'])
                    ->find($id);
            });
    }

    /**
     * Find coupon by code
     */
    public function findByCode(string $code): ?Coupon
    {
        return Cache::tags(['tenant', tenant()->id, 'coupons'])
            ->remember("coupon_code:{$code}", 3600, function () use ($code) {
                return Coupon::byCode($code)->first();
            });
    }

    /**
     * Get all active coupons
     */
    public function getActiveCoupons(): Collection
    {
        return Cache::tags(['tenant', tenant()->id, 'coupons'])
            ->remember('active_coupons', 1800, function () {
                return Coupon::available()
                    ->with(['products', 'categories', 'brands'])
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
    }

    /**
     * Get available coupons for customers
     */
    public function getAvailableCoupons(): Collection
    {
        return Cache::tags(['tenant', tenant()->id, 'coupons'])
            ->remember('available_coupons_public', 1800, function () {
                return Coupon::available()
                    ->notExhausted()
                    ->select([
                        'id',
                        'code',
                        'description',
                        'discount_type',
                        'discount_value',
                        'min_purchase_amount',
                        'max_discount_amount',
                        'valid_from',
                        'valid_until',
                        'applicable_to',
                        'is_active',
                        'usage_limit',
                        'usage_count',
                        'usage_limit_per_customer'
                    ])
                    ->orderBy('created_at', 'desc')
                    ->get();
            });
    }

    /**
     * Create new coupon
     */
    public function create(array $data): Coupon
    {
        return DB::transaction(function () use ($data) {
            $coupon = Coupon::create($data);

            $this->invalidateCache();

            return $coupon->load(['products', 'categories', 'brands']);
        });
    }

    /**
     * Update coupon
     */
    public function update(Coupon $coupon, array $data): Coupon
    {
        DB::transaction(function () use ($coupon, $data) {
            $coupon->update($data);

            $this->invalidateCache($coupon->id);
        });

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Delete coupon
     */
    public function delete(Coupon $coupon): bool
    {
        return DB::transaction(function () use ($coupon) {
            $deleted = $coupon->delete();

            if ($deleted) {
                $this->invalidateCache($coupon->id);
            }

            return $deleted;
        });
    }

    /**
     * Activate coupon
     */
    public function activate(Coupon $coupon): Coupon
    {
        DB::transaction(function () use ($coupon) {
            $coupon->update(['is_active' => true]);
            $this->invalidateCache($coupon->id);
        });

        return $coupon->fresh();
    }

    /**
     * Deactivate coupon
     */
    public function deactivate(Coupon $coupon): Coupon
    {
        DB::transaction(function () use ($coupon) {
            $coupon->update(['is_active' => false]);
            $this->invalidateCache($coupon->id);
        });

        return $coupon->fresh();
    }

    /**
     * Attach products to coupon
     */
    public function attachProducts(Coupon $coupon, array $productsData): void
    {
        DB::transaction(function () use ($coupon, $productsData) {
            // Format: [product_id => ['product_variant_id' => variant_id_or_null]]
            $syncData = [];
            foreach ($productsData as $productData) {
                $productId = $productData['product_id'];
                $variantId = $productData['product_variant_id'] ?? null;

                $syncData[$productId] = ['product_variant_id' => $variantId];
            }

            $coupon->products()->syncWithoutDetaching($syncData);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Detach product from coupon
     */
    public function detachProduct(Coupon $coupon, int $productId): void
    {
        DB::transaction(function () use ($coupon, $productId) {
            $coupon->products()->detach($productId);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Attach categories to coupon
     */
    public function attachCategories(Coupon $coupon, array $categoryIds): void
    {
        DB::transaction(function () use ($coupon, $categoryIds) {
            $coupon->categories()->syncWithoutDetaching($categoryIds);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Detach category from coupon
     */
    public function detachCategory(Coupon $coupon, int $categoryId): void
    {
        DB::transaction(function () use ($coupon, $categoryId) {
            $coupon->categories()->detach($categoryId);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Attach brands to coupon
     */
    public function attachBrands(Coupon $coupon, array $brandIds): void
    {
        DB::transaction(function () use ($coupon, $brandIds) {
            $coupon->brands()->syncWithoutDetaching($brandIds);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Detach brand from coupon
     */
    public function detachBrand(Coupon $coupon, int $brandId): void
    {
        DB::transaction(function () use ($coupon, $brandId) {
            $coupon->brands()->detach($brandId);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Bulk attach products
     */
    public function bulkAttachProducts(Coupon $coupon, array $productsData): void
    {
        $this->attachProducts($coupon, $productsData);
    }

    /**
     * Bulk detach products
     */
    public function bulkDetachProducts(Coupon $coupon, array $productIds): void
    {
        DB::transaction(function () use ($coupon, $productIds) {
            $coupon->products()->detach($productIds);

            $this->invalidateCache($coupon->id);
        });
    }

    /**
     * Check if code exists (tenant-scoped)
     */
    public function codeExists(string $code, ?int $excludeId = null): bool
    {
        $query = Coupon::byCode($code);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Invalidate cache
     */
    protected function invalidateCache(?int $couponId = null): void
    {
        // Invalidate all coupon-related cache
        Cache::tags(['tenant', tenant()->id, 'coupons'])->flush();

        if ($couponId) {
            Cache::tags(['tenant', tenant()->id, 'coupons'])->forget("coupon:{$couponId}");
        }
    }
}
