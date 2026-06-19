<?php

namespace App\Services\Tenant\Supplier;

use App\Enums\Tenant\PurchaseOrderStatus;
use App\Models\Tenant\Supplier;
use App\Repositories\Tenant\SupplierRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    public function __construct(
        protected SupplierRepository $repository
    ) {}

    /**
     * Get all suppliers
     */
    public function getAllSuppliers(array $filters = [], bool $paginate = false, int $perPage = 15): Collection|LengthAwarePaginator
    {
        $cacheKey = $this->getCacheKey('all', $filters, $paginate, $perPage);

        return Cache::tags(['tenant', tenant()->id, 'suppliers'])
            ->remember($cacheKey, 3600, function () use ($filters, $paginate, $perPage) {
                if ($paginate) {
                    return $this->repository->getPaginated($filters, $perPage);
                }

                return $this->repository->getAll($filters);
            });
    }

    /**
     * Get supplier by ID with products
     */
    public function getSupplierById(int $id, bool $withProducts = false): ?Supplier
    {
        $cacheKey = $this->getCacheKey('single', ['id' => $id, 'with_products' => $withProducts]);

        return Cache::tags(['tenant', tenant()->id, 'suppliers'])
            ->remember($cacheKey, 3600, function () use ($id, $withProducts) {
                if ($withProducts) {
                    return $this->repository->findByIdWithProducts($id);
                }

                return $this->repository->findById($id);
            });
    }

    /**
     * Create supplier with personal details
     */
    public function createSupplierPersonalDetails(array $data): Supplier
    {
        $this->validateUniqueness($data);

        return DB::transaction(function () use ($data) {
            $supplier = $this->repository->create($data);

            $this->clearCache();

            return $supplier;
        });
    }

    /**
     * Update supplier personal details
     */
    public function updateSupplierPersonalDetails(int $id, array $data): Supplier
    {
        $this->validateUniqueness($data, $id);

        return DB::transaction(function () use ($id, $data) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $this->repository->update($supplier, $data);

            $this->clearCache();

            return $supplier->fresh();
        });
    }

    /**
     * Update supplier financial details
     */
    public function updateSupplierFinancialDetails(int $id, array $data): Supplier
    {
        return DB::transaction(function () use ($id, $data) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $this->repository->update($supplier, $data);

            $this->clearCache();

            return $supplier->fresh();
        });
    }

    /**
     * Toggle supplier active status
     */
    public function toggleActiveStatus(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $supplier = $this->repository->findById($id);

            if (!$supplier) {
                throw new \InvalidArgumentException('Supplier not found');
            }

            $newStatus = !$supplier->is_active;
            $this->repository->update($supplier, ['is_active' => $newStatus]);

            $this->clearCache();

            return [
                'is_active' => $newStatus,
                'message' => $newStatus ? 'Supplier activated successfully' : 'Supplier deactivated successfully'
            ];
        });
    }

    /**
     * Get comprehensive financial summary for a supplier
     * 
     * @param int $supplierId
     * @return array
     */
    public function getSupplierFinancialSummary(int $supplierId): array
    {
        $supplier = Supplier::with(['purchaseOrders', 'payments'])->findOrFail($supplierId);

        // Total purchase orders (only counted statuses)
        $totalPurchases = $supplier->purchaseOrders()
            ->whereIn('status', [
                PurchaseOrderStatus::SENT,
                PurchaseOrderStatus::CONFIRMED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
                PurchaseOrderStatus::RECEIVED,
            ])
            ->sum('total_amount');

        // Total payments made
        $totalPaid = $supplier->payments()->sum('amount');

        // Outstanding balance (from supplier record - this is the source of truth)
        $outstandingBalance = $supplier->outstanding_balance;

        // Calculate what outstanding SHOULD be (for verification)
        $calculatedOutstanding = $totalPurchases - $totalPaid;

        // Check for balance mismatch (with tolerance for floating point)
        $balanceMismatch = abs($calculatedOutstanding - $outstandingBalance) > 0.01;

        // Credit limit utilization
        $creditUtilization = $supplier->credit_limit > 0
            ? ($outstandingBalance / $supplier->credit_limit) * 100
            : 0;

        // Payment statistics
        $paymentCount = $supplier->payments()->count();
        $lastPayment = $supplier->payments()->latest('payment_date')->first();

        // Purchase order statistics
        $poCount = $supplier->purchaseOrders()->count();
        $activePOCount = $supplier->purchaseOrders()
            ->whereIn('status', [
                PurchaseOrderStatus::SENT,
                PurchaseOrderStatus::CONFIRMED,
                PurchaseOrderStatus::PARTIALLY_RECEIVED,
            ])
            ->count();

        return [
            'supplier_id' => $supplierId,
            'supplier_name' => $supplier->name,
            'supplier_type' => $supplier->supplier_type->value,
            'is_active' => $supplier->is_active,

            // Financial metrics
            'total_purchases' => round($totalPurchases, 2),
            'total_paid' => round($totalPaid, 2),
            'outstanding_balance' => round($outstandingBalance, 2),
            'calculated_outstanding' => round($calculatedOutstanding, 2),
            'balance_mismatch' => $balanceMismatch,

            // Credit information
            'credit_limit' => round($supplier->credit_limit, 2),
            'credit_available' => round(max(0, $supplier->credit_limit - $outstandingBalance), 2),
            'credit_utilization_percent' => round($creditUtilization, 2),

            // Statistics
            'payment_count' => $paymentCount,
            'purchase_order_count' => $poCount,
            'active_purchase_orders' => $activePOCount,

            // Last payment info
            'last_payment' => $lastPayment ? [
                'payment_number' => $lastPayment->payment_number,
                'amount' => round($lastPayment->amount, 2),
                'payment_date' => $lastPayment->payment_date->format('Y-m-d'),
                'payment_method' => $lastPayment->payment_method->label(),
            ] : null,

            // Ratings
            'supplier_rating' => round($supplier->rating, 2),
            'total_orders' => $supplier->total_orders,

            'currency' => 'KES',
        ];
    }

    /**
     * Validate that supplier name and email combination is unique
     * 
     */
    protected function validateUniqueness(array $data, ?int $excludeId = null): void
    {
        $errors = [];

        // Check if name is provided and validate uniqueness
        if (isset($data['name'])) {
            $nameExists = $this->repository->existsByName($data['name'], $excludeId);
            if ($nameExists) {
                $errors['name'] = ['A supplier with this name already exists.'];
            }
        }

        // Check if email is provided and validate uniqueness
        if (isset($data['email']) && !empty($data['email'])) {
            $emailExists = $this->repository->existsByEmail($data['email'], $excludeId);
            if ($emailExists) {
                $errors['email'] = ['A supplier with this email already exists.'];
            }
        }

        // Check if both name and email combination exists
        if (isset($data['name']) && isset($data['email']) && !empty($data['email'])) {
            $combinationExists = $this->repository->existsByNameAndEmail(
                $data['name'],
                $data['email'],
                $excludeId
            );

            if ($combinationExists && empty($errors)) {
                $errors['supplier'] = ['A supplier with this name and email combination already exists.'];
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $type, array $params = []): string
    {
        return sprintf(
            'supplier:%s:%s',
            $type,
            md5(json_encode($params))
        );
    }

    /**
     * Clear all supplier cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'suppliers'])->flush();
    }
}
