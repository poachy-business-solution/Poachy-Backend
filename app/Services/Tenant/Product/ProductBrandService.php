<?php

namespace App\Services\Tenant\Product;

use App\Models\Tenant\ProductBrand;
use App\Repositories\Tenant\ProductBrandRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductBrandService
{
    public function __construct(
        protected ProductBrandRepository $repository
    ) {}

    /**
     * Get all brands with optional filtering
     */
    public function getAllBrands(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('all', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'product_brands'])
            ->remember($cacheKey, 3600, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginated($filters, $perPage);
                }

                return $this->repository->getAll($filters);
            });
    }

    /**
     * Get brand by ID with products
     */
    public function getBrandById(int $id, bool $withProducts = false): ?ProductBrand
    {
        $cacheKey = $this->getCacheKey('single', ['id' => $id, 'with_products' => $withProducts]);

        return Cache::tags(['tenant', tenant()->id, 'product_brands'])
            ->remember($cacheKey, 3600, function () use ($id, $withProducts) {
                if ($withProducts) {
                    return $this->repository->findByIdWithProducts($id);
                }

                return $this->repository->findById($id);
            });
    }

    /**
     * Create a new brand
     */
    public function createBrand(array $data): ProductBrand
    {
        return DB::transaction(function () use ($data) {
            // Handle logo upload
            if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
                $logoPath = $this->uploadLogo($data['logo']);
                $data['logo_url'] = $logoPath;
                unset($data['logo']);
            }

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            } else {
                // Validate slug uniqueness
                if ($this->repository->slugExists($data['slug'])) {
                    throw new \InvalidArgumentException('Brand already exists');
                }
            }

            $brand = $this->repository->create($data);

            // Clear cache
            $this->clearCache();

            return $brand;
        });
    }

    /**
     * Activate a brand
     */
    public function activateBrand(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $brand = $this->repository->findById($id);

            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            $result = $this->repository->update($brand, ['is_active' => true]);

            // Clear cache
            $this->clearCache();

            return $result;
        });
    }

    /**
     * Deactivate a brand
     */
    public function deactivateBrand(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $brand = $this->repository->findById($id);

            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            $result = $this->repository->update($brand, ['is_active' => false]);

            // Clear cache
            $this->clearCache();

            return $result;
        });
    }

    /**
     * Feature a brand
     */
    public function featureBrand(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $brand = $this->repository->findById($id);

            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            $result = $this->repository->update($brand, ['is_featured' => true]);

            // Clear cache
            $this->clearCache();

            return $result;
        });
    }

    /**
     * Unfeature a brand
     */
    public function unfeatureBrand(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $brand = $this->repository->findById($id);

            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            $result = $this->repository->update($brand, ['is_featured' => false]);

            // Clear cache
            $this->clearCache();

            return $result;
        });
    }

    /**
     * Update brand logo
     */
    public function updateBrandLogo(int $id, UploadedFile $logoFile): bool
    {
        return DB::transaction(function () use ($id, $logoFile) {
            $brand = $this->repository->findById($id);
            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            // Delete old logo if exists
            if ($brand->logo_url) {
                $this->deleteLogo($brand->logo_url);
            }

            // Upload new logo with original filename + timestamp
            $logoPath = $this->uploadLogo($logoFile);

            // Update brand with new logo path
            $result = $this->repository->update($brand, ['logo_url' => $logoPath]);

            // Clear cache
            $this->clearCache();

            return $result;
        });
    }

    /**
     * Delete a brand
     */
    public function deleteBrand(int $id): bool
    {
        return DB::connection('tenant')->transaction(function () use ($id) {
            $brand = $this->repository->findById($id);

            if (!$brand) {
                throw new \InvalidArgumentException('Brand not found');
            }

            // Check if brand has products
            if ($this->repository->hasProducts($brand)) {
                throw new \InvalidArgumentException('Cannot delete brand with associated products');
            }

            // Delete logo if exists
            if ($brand->logo_url) {
                $this->deleteLogo($brand->logo_url);
            }

            $deleted = $this->repository->delete($brand);

            // Clear cache
            $this->clearCache();

            return $deleted;
        });
    }

    protected function uploadLogo(UploadedFile $file): string
    {
        // Get original filename without extension
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();

        // Create filename with timestamp
        $filename = $originalName . '_' . time() . '.' . $extension;

        // Store in public disk under products/brands/logos
        $path = $file->storeAs('products/brands/logos', $filename, 'public');

        return $path;
    }

    protected function deleteLogo(string $logoPath): void
    {
        if (Storage::disk('public')->exists($logoPath)) {
            Storage::disk('public')->delete($logoPath);
        }
    }

    protected function generateUniqueSlug(string $name, int $attempt = 0): string
    {
        $slug = Str::slug($name);

        if ($attempt > 0) {
            $slug .= '-' . $attempt;
        }

        if ($this->repository->slugExists($slug)) {
            return $this->generateUniqueSlug($name, $attempt + 1);
        }

        return $slug;
    }

    protected function getCacheKey(string $type, array $params = []): string
    {
        return sprintf(
            'brand:%s:%s',
            $type,
            md5(json_encode($params))
        );
    }

    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'product_brands'])->flush();
    }
}
