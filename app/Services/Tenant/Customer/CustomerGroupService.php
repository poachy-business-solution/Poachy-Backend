<?php

namespace App\Services\Tenant\Customer;

use App\Models\Tenant\Customer;
use App\Models\Tenant\CustomerGroup;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerGroupService
{
    /**
     * Get paginated customer groups with filters
     */
    public function getPaginatedGroups(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CustomerGroup::query()->withCount('customers');

        // Apply filters
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filters['requires_approval'])) {
            $query->where('requires_approval', filter_var($filters['requires_approval'], FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortField, $sortOrder);

        return $query->paginate($perPage);
    }

    /**
     * Get customer group by ID
     */
    public function getGroupById(int $id): ?CustomerGroup
    {
        return CustomerGroup::withCount('customers')->find($id);
    }

    /**
     * Create new customer group
     */
    public function createGroup(array $data): CustomerGroup
    {
        return DB::transaction(function () use ($data) {
            $group = CustomerGroup::create($data);

            // Clear cache
            $this->clearGroupCache();

            Log::info('Customer group created', [
                'tenant_id' => tenant()->id,
                'group_id' => $group->id,
                'group_name' => $group->name,
            ]);

            return $group->fresh();
        });
    }

    /**
     * Update customer group
     */
    public function updateGroup(CustomerGroup $group, array $data): CustomerGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->update($data);

            // Clear cache
            $this->clearGroupCache();

            Log::info('Customer group updated', [
                'tenant_id' => tenant()->id,
                'group_id' => $group->id,
                'changes' => $group->getChanges(),
            ]);

            return $group->fresh();
        });
    }

    /**
     * Delete customer group
     */
    public function deleteGroup(CustomerGroup $group): bool
    {
        return DB::transaction(function () use ($group) {
            // Check if group has members
            $membersCount = $group->customers()->count();

            if ($membersCount > 0) {
                throw new \RuntimeException(
                    "Cannot delete group with {$membersCount} active members. Remove members first."
                );
            }

            $deleted = $group->delete();

            if ($deleted) {
                // Clear cache
                $this->clearGroupCache();

                Log::warning('Customer group deleted', [
                    'tenant_id' => tenant()->id,
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Toggle customer group status
     */
    public function toggleGroupStatus(CustomerGroup $group): CustomerGroup
    {
        return DB::transaction(function () use ($group) {
            $newStatus = !$group->is_active;

            $group->update([
                'is_active' => $newStatus,
            ]);

            // Clear cache
            $this->clearGroupCache();

            Log::info('Customer group status toggled', [
                'tenant_id' => tenant()->id,
                'group_id' => $group->id,
                'is_active' => $newStatus,
            ]);

            return $group->fresh();
        });
    }

    /**
     * Get group members (paginated)
     */
    public function getGroupMembers(CustomerGroup $group, int $perPage = 15): LengthAwarePaginator
    {
        return $group->customers()
            ->with(['preferredStore'])
            ->withPivot('joined_at')
            ->orderByPivot('joined_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Add customer to group
     */
    public function addMemberToGroup(CustomerGroup $group, int $customerId): bool
    {
        return DB::transaction(function () use ($group, $customerId) {
            $customer = Customer::findOrFail($customerId);

            // Check if customer is already in this group
            if ($group->hasCustomer($customerId)) {
                throw new \RuntimeException('Customer is already a member of this group');
            }

            // Remove customer from any other group (one group at a time)
            $currentGroup = $customer->getActiveGroup();
            if ($currentGroup) {
                $this->removeMemberFromGroup($currentGroup, $customerId);
            }

            // Add to new group
            $added = $group->addCustomer($customerId);

            if ($added) {
                // Clear cache
                $this->clearGroupCache();

                Log::info('Customer added to group', [
                    'tenant_id' => tenant()->id,
                    'group_id' => $group->id,
                    'customer_id' => $customerId,
                ]);
            }

            return $added;
        });
    }

    /**
     * Remove customer from group
     */
    public function removeMemberFromGroup(CustomerGroup $group, int $customerId): bool
    {
        return DB::transaction(function () use ($group, $customerId) {
            $removed = $group->removeCustomer($customerId);

            if ($removed) {
                // Clear cache
                $this->clearGroupCache();

                Log::info('Customer removed from group', [
                    'tenant_id' => tenant()->id,
                    'group_id' => $group->id,
                    'customer_id' => $customerId,
                ]);
            }

            return $removed;
        });
    }

    /**
     * Bulk add customers to group
     */
    public function bulkAddMembers(CustomerGroup $group, array $customerIds): array
    {
        return DB::transaction(function () use ($group, $customerIds) {
            $results = [
                'added' => [],
                'skipped' => [],
                'failed' => [],
            ];

            foreach ($customerIds as $customerId) {
                try {
                    // Validate customer exists
                    $customer = Customer::find($customerId);
                    if (!$customer) {
                        $results['failed'][] = [
                            'customer_id' => $customerId,
                            'reason' => 'Customer not found',
                        ];
                        continue;
                    }

                    // Check if already in group
                    if ($group->hasCustomer($customerId)) {
                        $results['skipped'][] = [
                            'customer_id' => $customerId,
                            'reason' => 'Already in group',
                        ];
                        continue;
                    }

                    // Remove from current group if any
                    $currentGroup = $customer->getActiveGroup();
                    if ($currentGroup) {
                        $currentGroup->removeCustomer($customerId);
                    }

                    // Add to new group
                    $group->addCustomer($customerId);
                    $results['added'][] = $customerId;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'customer_id' => $customerId,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            // Clear cache
            $this->clearGroupCache();

            Log::info('Bulk members added to group', [
                'tenant_id' => tenant()->id,
                'group_id' => $group->id,
                'added_count' => count($results['added']),
                'skipped_count' => count($results['skipped']),
                'failed_count' => count($results['failed']),
            ]);

            return $results;
        });
    }

    /**
     * Clear group cache
     */
    private function clearGroupCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'customer_groups'])->flush();
    }
}
