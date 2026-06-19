<?php

namespace App\Services\Tenant\Offers;

use App\Enums\Tenant\PromotionApplicabilityType;
use App\Events\Tenant\PromotionActivated;
use App\Events\Tenant\PromotionCreated;
use App\Events\Tenant\PromotionDeactivated;
use App\Events\Tenant\PromotionUpdated;
use App\Models\Tenant\Promotion;
use App\Repositories\Tenant\PromotionRepository;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function __construct(
        protected PromotionRepository $promotionRepository
    ) {}

    /**
     * Get paginated promotions with filters
     */
    public function getPaginatedPromotions(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->promotionRepository->getPaginated($filters, $perPage);
    }

    /**
     * Get promotion by ID
     */
    public function getPromotionById(int $id): ?Promotion
    {
        return $this->promotionRepository->findById($id);
    }

    /**
     * Get currently running promotions
     */
    public function getCurrentlyRunning(?int $storeId = null, ?Carbon $now = null): Collection
    {
        return $this->promotionRepository->getCurrentlyRunning($storeId, $now);
    }

    /**
     * Get featured promotions
     */
    public function getFeaturedPromotions(?int $storeId = null): Collection
    {
        return $this->promotionRepository->getFeatured($storeId);
    }

    /**
     * Get POS promotions
     */
    public function getPosPromotions(?int $storeId = null): Collection
    {
        return $this->promotionRepository->getPosPromotions($storeId);
    }

    /**
     * Get website promotions
     */
    public function getWebsitePromotions(?int $storeId = null): Collection
    {
        return $this->promotionRepository->getWebsitePromotions($storeId);
    }

    /**
     * Create new promotion
     */
    public function createPromotion(array $data): Promotion
    {
        // Validate business rules
        $this->validatePromotionData($data);

        // Extract applicability data
        $applicabilityData = $data['applicability'] ?? null;
        unset($data['applicability']);

        // Create promotion
        $promotion = $this->promotionRepository->create($data);

        // Attach related entities based on applicability type
        if ($applicabilityData) {
            $this->syncApplicability($promotion, $applicabilityData);
        }

        // Dispatch event
        event(new PromotionCreated($promotion));

        Log::info('Promotion created', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'code' => $promotion->code,
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Update promotion
     */
    public function updatePromotion(Promotion $promotion, array $data): Promotion
    {
        // Check if promotion can be edited
        if (!$promotion->canBeEdited() && $this->hasRestrictedChanges($data, $promotion)) {
            throw ValidationException::withMessages([
                'promotion' => ['Cannot modify core attributes of a promotion that has already been used.'],
            ]);
        }

        // Validate business rules
        $this->validatePromotionData($data, $promotion->id);

        // Extract applicability data
        $applicabilityData = $data['applicability'] ?? null;
        unset($data['applicability']);

        // Check if changing applicability type
        if (isset($data['applicable_to']) && $data['applicable_to'] !== $promotion->applicable_to->value) {
            if (!$promotion->canChangeApplicabilityType()) {
                throw ValidationException::withMessages([
                    'applicable_to' => ['Cannot change applicability type when promotion already has related data.'],
                ]);
            }
        }

        // Update promotion
        $updatedPromotion = $this->promotionRepository->update($promotion, $data);

        // Update applicability if provided
        if ($applicabilityData) {
            $this->syncApplicability($updatedPromotion, $applicabilityData);
        }

        // Dispatch event
        event(new PromotionUpdated($updatedPromotion));

        Log::info('Promotion updated', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $updatedPromotion->id,
            'code' => $updatedPromotion->code,
        ]);

        return $updatedPromotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Delete promotion
     */
    public function deletePromotion(Promotion $promotion): bool
    {
        $deleted = $this->promotionRepository->delete($promotion);

        if ($deleted) {
            Log::info('Promotion deleted', [
                'tenant_id' => tenant()->id,
                'promotion_id' => $promotion->id,
                'code' => $promotion->code,
            ]);
        }

        return $deleted;
    }

    /**
     * Activate promotion
     */
    public function activatePromotion(Promotion $promotion): Promotion
    {
        if ($promotion->is_expired) {
            throw ValidationException::withMessages([
                'promotion' => ['Cannot activate an expired promotion.'],
            ]);
        }

        if ($promotion->is_active) {
            throw ValidationException::withMessages([
                'promotion' => ['Promotion is already active.'],
            ]);
        }

        $activatedPromotion = $this->promotionRepository->activate($promotion);

        event(new PromotionActivated($activatedPromotion));

        Log::info('Promotion activated', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $activatedPromotion->id,
            'code' => $activatedPromotion->code,
        ]);

        return $activatedPromotion;
    }

    /**
     * Deactivate promotion
     */
    public function deactivatePromotion(Promotion $promotion): Promotion
    {
        if (!$promotion->is_active) {
            throw ValidationException::withMessages([
                'promotion' => ['Promotion is already inactive.'],
            ]);
        }

        $deactivatedPromotion = $this->promotionRepository->deactivate($promotion);

        event(new PromotionDeactivated($deactivatedPromotion));

        Log::info('Promotion deactivated', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $deactivatedPromotion->id,
            'code' => $deactivatedPromotion->code,
        ]);

        return $deactivatedPromotion;
    }

    /**
     * Attach products to promotion
     */
    public function attachProducts(Promotion $promotion, array $productsData): Promotion
    {
        if (!in_array($promotion->applicable_to, [
            PromotionApplicabilityType::SPECIFIC_PRODUCTS,
            PromotionApplicabilityType::ALL_PRODUCTS
        ])) {
            throw ValidationException::withMessages([
                'applicable_to' => ['Can only attach products to promotions with applicable_to set to "specific_products".'],
            ]);
        }

        $this->promotionRepository->attachProducts($promotion, $productsData);

        Log::info('Products attached to promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'products_count' => count($productsData),
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach product from promotion
     */
    public function detachProduct(Promotion $promotion, int $productId): Promotion
    {
        $this->promotionRepository->detachProduct($promotion, $productId);

        Log::info('Product detached from promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'product_id' => $productId,
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Attach categories to promotion
     */
    public function attachCategories(Promotion $promotion, array $categoryIds): Promotion
    {
        if (! in_array($promotion->applicable_to, [
            PromotionApplicabilityType::SPECIFIC_CATEGORIES,
            PromotionApplicabilityType::ALL_PRODUCTS
        ], true)) {
            throw ValidationException::withMessages([
                'applicable_to' => [
                    'Categories can only be attached when applicable_to is "specific_categories" or "all_products".'
                ],
            ]);
        }

        $this->promotionRepository->attachCategories($promotion, $categoryIds);

        Log::info('Categories attached to promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'categories_count' => count($categoryIds),
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach category from promotion
     */
    public function detachCategory(Promotion $promotion, int $categoryId): Promotion
    {
        $this->promotionRepository->detachCategory($promotion, $categoryId);

        Log::info('Category detached from promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'category_id' => $categoryId,
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Attach brands to promotion
     */
    public function attachBrands(Promotion $promotion, array $brandIds): Promotion
    {
        if (! in_array($promotion->applicable_to, [
            PromotionApplicabilityType::SPECIFIC_BRANDS,
            PromotionApplicabilityType::ALL_PRODUCTS
        ], true)) {
            throw ValidationException::withMessages([
                'applicable_to' => [
                    'Brands can only be attached when applicable_to is "specific_brands" or "all_products".'
                ],
            ]);
        }

        $this->promotionRepository->attachBrands($promotion, $brandIds);

        Log::info('Brands attached to promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'brands_count' => count($brandIds),
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Detach brand from promotion
     */
    public function detachBrand(Promotion $promotion, int $brandId): Promotion
    {
        $this->promotionRepository->detachBrand($promotion, $brandId);

        Log::info('Brand detached from promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'brand_id' => $brandId,
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Bulk attach products
     */
    public function bulkAttachProducts(Promotion $promotion, array $productsData): Promotion
    {
        return $this->attachProducts($promotion, $productsData);
    }

    /**
     * Bulk detach products
     */
    public function bulkDetachProducts(Promotion $promotion, array $productIds): Promotion
    {
        $this->promotionRepository->bulkDetachProducts($promotion, $productIds);

        Log::info('Products bulk detached from promotion', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'products_count' => count($productIds),
        ]);

        return $promotion->fresh(['products', 'categories', 'brands']);
    }

    /**
     * Update applicable stores
     */
    public function updateApplicableStores(Promotion $promotion, ?array $storeIds): Promotion
    {
        $updatedPromotion = $this->promotionRepository->updateApplicableStores($promotion, $storeIds);

        Log::info('Promotion applicable stores updated', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'store_ids' => $storeIds,
        ]);

        return $updatedPromotion;
    }

    /**
     * Update applicable customer groups
     */
    public function updateApplicableCustomerGroups(Promotion $promotion, ?array $customerGroupIds): Promotion
    {
        $updatedPromotion = $this->promotionRepository->updateApplicableCustomerGroups($promotion, $customerGroupIds);

        Log::info('Promotion applicable customer groups updated', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'customer_group_ids' => $customerGroupIds,
        ]);

        return $updatedPromotion;
    }

    /**
     * Update promotion banner image
     */
    public function updateBanner(Promotion $promotion, UploadedFile $bannerImage): Promotion
    {
        $updatedPromotion = $this->promotionRepository->updateBanner($promotion, $bannerImage);

        event(new PromotionUpdated($updatedPromotion));

        return $updatedPromotion;
    }

    /**
     * Remove promotion banner image
     */
    public function removeBanner(Promotion $promotion): Promotion
    {
        $updatedPromotion = $this->promotionRepository->removeBanner($promotion);

        event(new PromotionUpdated($updatedPromotion));

        return $updatedPromotion;
    }

    /**
     * Validate promotion data
     */
    protected function validatePromotionData(array $data, ?int $excludeId = null): void
    {
        // Validate date range
        if (isset($data['start_date']) && isset($data['end_date'])) {
            if ($data['start_date'] > $data['end_date']) {
                throw ValidationException::withMessages([
                    'end_date' => ['End date must be after start date.'],
                ]);
            }
        }

        // Validate discount value for percentage
        if (isset($data['promotion_type']) && isset($data['discount_value'])) {
            if ($data['promotion_type'] === 'percentage_discount' && $data['discount_value'] > 100) {
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

        // Validate time window
        if (isset($data['active_time_start']) && isset($data['active_time_end'])) {
            if ($data['active_time_start'] >= $data['active_time_end']) {
                throw ValidationException::withMessages([
                    'active_time_end' => ['End time must be after start time.'],
                ]);
            }
        }
    }

    /**
     * Sync applicability
     */
    protected function syncApplicability(Promotion $promotion, array $applicabilityData): void
    {
        $applicabilityType = $promotion->applicable_to;

        match ($applicabilityType) {
            PromotionApplicabilityType::SPECIFIC_PRODUCTS => $this->syncProducts($promotion, $applicabilityData),
            PromotionApplicabilityType::SPECIFIC_CATEGORIES => $this->syncCategories($promotion, $applicabilityData),
            PromotionApplicabilityType::SPECIFIC_BRANDS => $this->syncBrands($promotion, $applicabilityData),
            default => null,
        };
    }

    /**
     * Sync products
     */
    protected function syncProducts(Promotion $promotion, array $applicabilityData): void
    {
        if (isset($applicabilityData['products']) && is_array($applicabilityData['products'])) {
            $this->promotionRepository->attachProducts($promotion, $applicabilityData['products']);
        }
    }

    /**
     * Sync categories
     */
    protected function syncCategories(Promotion $promotion, array $applicabilityData): void
    {
        if (isset($applicabilityData['categories']) && is_array($applicabilityData['categories'])) {
            $this->promotionRepository->attachCategories($promotion, $applicabilityData['categories']);
        }
    }

    /**
     * Sync brands
     */
    protected function syncBrands(Promotion $promotion, array $applicabilityData): void
    {
        if (isset($applicabilityData['brands']) && is_array($applicabilityData['brands'])) {
            $this->promotionRepository->attachBrands($promotion, $applicabilityData['brands']);
        }
    }

    /**
     * Check if update contains restricted changes
     */
    protected function hasRestrictedChanges(array $data, Promotion $promotion): bool
    {
        $restrictedFields = ['code', 'promotion_type', 'discount_value', 'applicable_to'];

        foreach ($restrictedFields as $field) {
            if (isset($data[$field]) && $data[$field] != $promotion->$field) {
                return true;
            }
        }

        return false;
    }
}
