<?php

namespace App\Services\Tenant\Offers;

use App\Enums\Tenant\CouponApplicabilityType;
use App\Events\Tenant\CouponActivated;
use App\Events\Tenant\CouponCreated;
use App\Events\Tenant\CouponDeactivated;
use App\Events\Tenant\CouponUpdated;
use App\Models\Tenant\Coupon;
use App\Repositories\Tenant\CouponRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function __construct(
        protected CouponRepository $couponRepository
    ) {}

    /**
     * Get paginated coupons with filters
     */
    public function getPaginatedCoupons(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->couponRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get coupon by ID
     */
    public function getCouponById(int $id): ?Coupon
    {
        return $this->couponRepository->findById($id);
    }

    /**
     * Get available coupons for customers
     */
    public function getAvailableCoupons(): Collection
    {
        return $this->couponRepository->getAvailableCoupons();
    }

    /**
     * Create new coupon
     */
    public function createCoupon(array $data): Coupon
    {
        // Validate business rules
        $this->validateCouponData($data);

        // Extract applicability data
        $applicabilityData = $data['applicability'] ?? null;
        unset($data['applicability']);

        // Create coupon
        $coupon = $this->couponRepository->create($data);

        // Attach related entities based on applicability type
        if ($applicabilityData) {
            $this->syncApplicability($coupon, $applicabilityData);
        }

        // Dispatch event
        event(new CouponCreated($coupon));

        Log::info('Coupon created', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Update coupon
     */
    public function updateCoupon(Coupon $coupon, array $data): Coupon
    {
        // Check if coupon can be edited
        if (!$coupon->canBeEdited() && $this->hasRestrictedChanges($data, $coupon)) {
            throw ValidationException::withMessages([
                'coupon' => ['Cannot modify core attributes of a coupon that has already been used.'],
            ]);
        }

        // Validate business rules
        $this->validateCouponData($data, $coupon->id);

        // Extract applicability data
        $applicabilityData = $data['applicability'] ?? null;
        unset($data['applicability']);

        // Check if changing applicability type
        if (isset($data['applicable_to']) && $data['applicable_to'] !== $coupon->applicable_to->value) {
            if (!$coupon->canChangeApplicabilityType()) {
                throw ValidationException::withMessages([
                    'applicable_to' => ['Cannot change applicability type when coupon already has related products, categories, or brands attached.'],
                ]);
            }
        }

        // Update coupon
        $updatedCoupon = $this->couponRepository->update($coupon, $data);

        // Update applicability if provided
        if ($applicabilityData) {
            $this->syncApplicability($updatedCoupon, $applicabilityData);
        }

        // Dispatch event
        event(new CouponUpdated($updatedCoupon));

        Log::info('Coupon updated', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $updatedCoupon->id,
            'code' => $updatedCoupon->code,
        ]);

        return $updatedCoupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Delete coupon
     */
    public function deleteCoupon(Coupon $coupon): bool
    {
        $deleted = $this->couponRepository->delete($coupon);

        if ($deleted) {
            Log::info('Coupon deleted', [
                'tenant_id' => tenant()->id,
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
            ]);
        }

        return $deleted;
    }

    /**
     * Activate coupon
     */
    public function activateCoupon(Coupon $coupon): Coupon
    {
        // Validate coupon can be activated
        if ($coupon->is_expired) {
            throw ValidationException::withMessages([
                'coupon' => ['Cannot activate an expired coupon.'],
            ]);
        }

        if ($coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => ['Coupon is already active.'],
            ]);
        }

        $activatedCoupon = $this->couponRepository->activate($coupon);

        // Dispatch event
        event(new CouponActivated($activatedCoupon));

        Log::info('Coupon activated', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $activatedCoupon->id,
            'code' => $activatedCoupon->code,
        ]);

        return $activatedCoupon;
    }

    /**
     * Deactivate coupon
     */
    public function deactivateCoupon(Coupon $coupon): Coupon
    {
        if (!$coupon->is_active) {
            throw ValidationException::withMessages([
                'coupon' => ['Coupon is already inactive.'],
            ]);
        }

        $deactivatedCoupon = $this->couponRepository->deactivate($coupon);

        // Dispatch event
        event(new CouponDeactivated($deactivatedCoupon));

        Log::info('Coupon deactivated', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $deactivatedCoupon->id,
            'code' => $deactivatedCoupon->code,
        ]);

        return $deactivatedCoupon;
    }

    /**
     * Attach products to coupon
     */
    public function attachProducts(Coupon $coupon, array $productsData): Coupon
    {
        // Validate applicability type
        if (!in_array($coupon->applicable_to, [
            CouponApplicabilityType::SPECIFIC_PRODUCTS,
            CouponApplicabilityType::ALL_PRODUCTS
        ])) {
            throw ValidationException::withMessages([
                'applicable_to' => ['Can only attach products to coupons with applicable_to set to "specific_products" or "all_products".'],
            ]);
        }

        $this->couponRepository->attachProducts($coupon, $productsData);

        Log::info('Products attached to coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'products_count' => count($productsData),
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach product from coupon
     */
    public function detachProduct(Coupon $coupon, int $productId): Coupon
    {
        $this->couponRepository->detachProduct($coupon, $productId);

        Log::info('Product detached from coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'product_id' => $productId,
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Attach categories to coupon
     */
    public function attachCategories(Coupon $coupon, array $categoryIds): Coupon
    {
        // Validate applicability type
        if ($coupon->applicable_to !== CouponApplicabilityType::SPECIFIC_CATEGORIES) {
            throw ValidationException::withMessages([
                'applicable_to' => ['Can only attach categories to coupons with applicable_to set to "specific_categories".'],
            ]);
        }

        $this->couponRepository->attachCategories($coupon, $categoryIds);

        Log::info('Categories attached to coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'categories_count' => count($categoryIds),
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach category from coupon
     */
    public function detachCategory(Coupon $coupon, int $categoryId): Coupon
    {
        $this->couponRepository->detachCategory($coupon, $categoryId);

        Log::info('Category detached from coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'category_id' => $categoryId,
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Attach brands to coupon
     */
    public function attachBrands(Coupon $coupon, array $brandIds): Coupon
    {
        // Validate applicability type
        if ($coupon->applicable_to !== CouponApplicabilityType::SPECIFIC_BRANDS) {
            throw ValidationException::withMessages([
                'applicable_to' => ['Can only attach brands to coupons with applicable_to set to "specific_brands".'],
            ]);
        }

        $this->couponRepository->attachBrands($coupon, $brandIds);

        Log::info('Brands attached to coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'brands_count' => count($brandIds),
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach brand from coupon
     */
    public function detachBrand(Coupon $coupon, int $brandId): Coupon
    {
        $this->couponRepository->detachBrand($coupon, $brandId);

        Log::info('Brand detached from coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'brand_id' => $brandId,
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Bulk attach products
     */
    public function bulkAttachProducts(Coupon $coupon, array $productsData): Coupon
    {
        return $this->attachProducts($coupon, $productsData);
    }

    /**
     * Bulk detach products
     */
    public function bulkDetachProducts(Coupon $coupon, array $productIds): Coupon
    {
        $this->couponRepository->bulkDetachProducts($coupon, $productIds);

        Log::info('Products bulk detached from coupon', [
            'tenant_id' => tenant()->id,
            'coupon_id' => $coupon->id,
            'products_count' => count($productIds),
        ]);

        return $coupon->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Validate coupon data
     */
    protected function validateCouponData(array $data, ?int $excludeId = null): void
    {
        // Validate date range
        if (isset($data['valid_from']) && isset($data['valid_until'])) {
            if ($data['valid_from'] > $data['valid_until']) {
                throw ValidationException::withMessages([
                    'valid_until' => ['Valid until date must be after valid from date.'],
                ]);
            }
        }

        // Validate discount value
        if (isset($data['discount_type']) && isset($data['discount_value'])) {
            if ($data['discount_type'] === 'percentage' && $data['discount_value'] > 100) {
                throw ValidationException::withMessages([
                    'discount_value' => ['Percentage discount cannot exceed 100%.'],
                ]);
            }

            if ($data['discount_value'] <= 0) {
                throw ValidationException::withMessages([
                    'discount_value' => ['Discount value must be greater than 0.'],
                ]);
            }
        }

        // Validate usage limit
        if (isset($data['usage_limit']) && $data['usage_limit'] !== null && $data['usage_limit'] < 1) {
            throw ValidationException::withMessages([
                'usage_limit' => ['Usage limit must be at least 1.'],
            ]);
        }
    }

    /**
     * Sync applicability (products, categories, brands)
     */
    protected function syncApplicability(Coupon $coupon, array $applicabilityData): void
    {
        $applicabilityType = $coupon->applicable_to;

        match ($applicabilityType) {
            CouponApplicabilityType::SPECIFIC_PRODUCTS => $this->syncProducts($coupon, $applicabilityData),
            CouponApplicabilityType::SPECIFIC_CATEGORIES => $this->syncCategories($coupon, $applicabilityData),
            CouponApplicabilityType::SPECIFIC_BRANDS => $this->syncBrands($coupon, $applicabilityData),
            default => null,
        };
    }

    /**
     * Sync products
     */
    protected function syncProducts(Coupon $coupon, array $applicabilityData): void
    {
        if (isset($applicabilityData['products']) && is_array($applicabilityData['products'])) {
            $this->couponRepository->attachProducts($coupon, $applicabilityData['products']);
        }
    }

    /**
     * Sync categories
     */
    protected function syncCategories(Coupon $coupon, array $applicabilityData): void
    {
        if (isset($applicabilityData['categories']) && is_array($applicabilityData['categories'])) {
            $this->couponRepository->attachCategories($coupon, $applicabilityData['categories']);
        }
    }

    /**
     * Sync brands
     */
    protected function syncBrands(Coupon $coupon, array $applicabilityData): void
    {
        if (isset($applicabilityData['brands']) && is_array($applicabilityData['brands'])) {
            $this->couponRepository->attachBrands($coupon, $applicabilityData['brands']);
        }
    }

    /**
     * Check if update contains restricted changes
     */
    protected function hasRestrictedChanges(array $data, Coupon $coupon): bool
    {
        $restrictedFields = ['code', 'discount_type', 'discount_value', 'applicable_to'];

        foreach ($restrictedFields as $field) {
            if (isset($data[$field]) && $data[$field] != $coupon->$field) {
                return true;
            }
        }

        return false;
    }
}
