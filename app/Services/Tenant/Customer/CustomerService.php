<?php

namespace App\Services\Tenant\Customer;

use App\Enums\Tenant\CustomerType;
use App\Models\Tenant\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerService
{
    /**
     * Get paginated customers with filters
     */
    public function getPaginatedCustomers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()->with(['preferredStore', 'currentGroup.group']);

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['customer_type'])) {
            $customerType = CustomerType::from($filters['customer_type']);
            $query->byType($customerType);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (!empty($filters['group_id'])) {
            $query->whereHas('groups', function ($q) use ($filters) {
                $q->where('customer_groups.id', $filters['group_id']);
            });
        }

        if (!empty($filters['has_debt'])) {
            $query->withDebt();
        }

        // Sorting
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get customer by ID with relationships
     */
    public function getCustomerById(int $id): ?Customer
    {
        return Customer::with([
            'preferredStore',
            'groups',
            'currentGroup.group',
        ])->find($id);
    }

    /**
     * Create new customer
     */
    public function createCustomer(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $customer = Customer::create($data);

            // Clear cache
            $this->clearCustomerCache();

            Log::info('Customer created', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'customer_number' => $customer->customer_number,
            ]);

            return $customer->fresh(['preferredStore', 'currentGroup.group']);
        });
    }

    /**
     * Update customer
     */
    public function updateCustomer(Customer $customer, array $data): Customer
    {
        return DB::transaction(function () use ($customer, $data) {
            $customer->update($data);

            // Clear cache
            $this->clearCustomerCache();
            $this->clearCustomerStatsCache($customer->id);

            Log::info('Customer updated', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'changes' => $customer->getChanges(),
            ]);

            return $customer->fresh(['preferredStore', 'currentGroup.group']);
        });
    }

    /**
     * Soft delete customer
     */
    public function deleteCustomer(Customer $customer): bool
    {
        return DB::transaction(function () use ($customer) {
            $deleted = $customer->delete();

            if ($deleted) {
                // Clear cache
                $this->clearCustomerCache();
                $this->clearCustomerStatsCache($customer->id);

                Log::warning('Customer deleted', [
                    'tenant_id' => tenant()->id,
                    'customer_id' => $customer->id,
                    'customer_number' => $customer->customer_number,
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Restore soft deleted customer
     */
    public function restoreCustomer(int $id): ?Customer
    {
        return DB::transaction(function () use ($id) {
            $customer = Customer::withTrashed()->find($id);

            if (!$customer || !$customer->trashed()) {
                return null;
            }

            $customer->restore();

            // Clear cache
            $this->clearCustomerCache();

            Log::info('Customer restored', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'customer_number' => $customer->customer_number,
            ]);

            return $customer->fresh(['preferredStore', 'currentGroup.group']);
        });
    }

    /**
     * Search customers by query
     */
    public function searchCustomers(string $query, int $limit = 20): Collection
    {
        return Customer::search($query)
            ->active()
            ->with(['preferredStore'])
            ->limit($limit)
            ->get();
    }

    /**
     * Upgrade customer type
     */
    public function upgradeCustomerType(Customer $customer, CustomerType $targetType): Customer
    {
        if (!$customer->canUpgradeTo($targetType)) {
            throw new \InvalidArgumentException(
                "Cannot upgrade {$customer->customer_type->value} to {$targetType->value}"
            );
        }

        return DB::transaction(function () use ($customer, $targetType) {
            $oldType = $customer->customer_type;

            $customer->update([
                'customer_type' => $targetType,
            ]);

            // Clear cache
            $this->clearCustomerCache();
            $this->clearCustomerStatsCache($customer->id);

            Log::info('Customer type upgraded', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'old_type' => $oldType->value,
                'new_type' => $targetType->value,
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Toggle customer active status
     */
    public function toggleCustomerStatus(Customer $customer): Customer
    {
        return DB::transaction(function () use ($customer) {
            $newStatus = !$customer->is_active;

            $customer->update([
                'is_active' => $newStatus,
            ]);

            // Clear cache
            $this->clearCustomerCache();

            Log::info('Customer status toggled', [
                'tenant_id' => tenant()->id,
                'customer_id' => $customer->id,
                'is_active' => $newStatus,
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Get customer statistics
     */
    public function getCustomerStats(Customer $customer): array
    {
        $cacheKey = "customer_stats_{$customer->id}";

        return Cache::tags(['tenant', tenant()->id, 'customers'])
            ->remember($cacheKey, now()->addMinutes(15), function () use ($customer) {
                return [
                    'total_purchases' => $customer->total_lifetime_purchases,
                    'total_visits' => $customer->total_visits,
                    'loyalty_points' => $customer->loyalty_points,
                    'available_credit' => $customer->available_credit,
                    'current_debt' => $customer->current_debt,
                    'customer_since' => $customer->registered_at?->diffForHumans(),
                    'customer_type' => $customer->customer_type->label(),
                    'active_group' => $customer->getActiveGroup()?->name,
                ];
            });
    }

    /**
     * Clear customer cache
     */
    private function clearCustomerCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'customers'])->flush();
    }

    /**
     * Clear specific customer stats cache
     */
    private function clearCustomerStatsCache(int $customerId): void
    {
        $cacheKey = "customer_stats_{$customerId}";
        Cache::tags(['tenant', tenant()->id, 'customers'])->forget($cacheKey);
    }
}
