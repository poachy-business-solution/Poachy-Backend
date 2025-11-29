<?php

namespace App\Services\Central\Admin\Tenant;

use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantService
{
    /**
     * Create a new tenant with domain(s).
     */
    public function createTenant(array $data): Tenant
    {
        // Generate UUID for tenant
        $tenantId = (string) Str::uuid();

        // Prepare tenant metadata
        $tenantData = [];
        if (isset($data['tenant_name'])) {
            $tenantData['tenant_name'] = $data['tenant_name'];
        }
        if (isset($data['notes'])) {
            $tenantData['notes'] = $data['notes'];
        }

        try {
            // Create tenant
            $tenant = Tenant::create([
                'id' => $tenantId,
                'data' => $tenantData,
            ]);

            DB::setDefaultConnection('central');

            // Create primary domain
            Domain::create([
                'domain' => $data['domain'],
                'tenant_id' => $tenant->id,
            ]);

            // Create additional domains if provided
            if (!empty($data['additional_domains'])) {
                foreach ($data['additional_domains'] as $additionalDomain) {
                    Domain::create([
                        'domain' => $additionalDomain,
                        'tenant_id' => $tenant->id,
                    ]);
                }
            }

            Log::info('Tenant created successfully', [
                'tenant_id' => $tenant->id,
                'domain' => $data['domain'],
            ]);

            return $tenant->fresh(['domains']);
        } catch (\Exception $e) {
            Log::error('Tenant creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            // Cleanup: If tenant was created but domain creation failed
            if (isset($tenant) && $tenant->exists) {
                try {
                    // Delete the tenant (this will trigger cleanup events)
                    $tenant->delete();
                    Log::info('Cleaned up failed tenant', ['tenant_id' => $tenant->id]);
                } catch (\Exception $cleanupError) {
                    Log::error('Failed to cleanup tenant', [
                        'tenant_id' => $tenant->id,
                        'error' => $cleanupError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Get tenant with all relationships.
     */
    public function getTenant(string $tenantId): Tenant
    {
        return Tenant::with(['domains', 'businessDetail', 'activeSubscription'])
            ->findOrFail($tenantId);
    }

    /**
     * Get all tenants with pagination.
     */
    public function getAllTenants(int $perPage = 15)
    {
        return Tenant::with(['domains', 'businessDetail'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Add domain to existing tenant.
     */
    public function addDomain(string $tenantId, string $domain): Domain
    {
        $tenant = Tenant::findOrFail($tenantId);

        return Domain::create([
            'domain' => $domain,
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Update domain.
     */
    public function updateDomain(int $domainId, string $newDomain): Domain
    {
        return DB::connection('central')->transaction(function () use ($domainId, $newDomain) {
            $domain = Domain::findOrFail($domainId);

            $domain->update([
                'domain' => $newDomain,
            ]);

            return $domain->fresh();
        });
    }

    /**
     * Delete domain (prevent deletion of last domain).
     */
    public function deleteDomain(int $domainId): void
    {
        DB::connection('central')->transaction(function () use ($domainId) {
            $domain = Domain::findOrFail($domainId);

            // Check if this is the last domain for the tenant
            $domainCount = Domain::where('tenant_id', $domain->tenant_id)->count();

            if ($domainCount <= 1) {
                throw new \Exception('Cannot delete the last domain. A tenant must have at least one domain.');
            }

            $domain->delete();
        });
    }

    /**
     * Update tenant metadata.
     */
    public function updateTenantMetadata(string $tenantId, array $metadata): Tenant
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $metadata) {
            $tenant = Tenant::findOrFail($tenantId);

            $currentData = $tenant->data ?? [];
            $updatedData = array_merge($currentData, $metadata);

            $tenant->update([
                'data' => $updatedData,
            ]);

            return $tenant->fresh(['domains']);
        });
    }

    /**
     * Delete tenant and all associated data.
     */
    public function deleteTenant(string $tenantId): void
    {
        // Ensure we're on the central connection
        DB::setDefaultConnection('central');

        $tenant = Tenant::on('central')->findOrFail($tenantId);

        Log::info('Deleting tenant', [
            'tenant_id' => $tenantId,
            'database' => $tenant->getDatabaseName(),
            'domains_count' => $tenant->domains()->count(),
        ]);

        // This will cascade delete domains, business details, etc.
        // Database will also be deleted via TenantDeleted event
        $tenant->delete();

        Log::info('Tenant deleted successfully', [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Search tenants by domain or business name.
     */
    public function searchTenants(string $query, int $perPage = 15)
    {
        return Tenant::with(['domains', 'businessDetail'])
            ->where(function ($q) use ($query) {
                // Search in tenant metadata
                $q->where('data->tenant_name', 'like', "%{$query}%")
                    // Or search in domains
                    ->orWhereHas('domains', function ($domainQuery) use ($query) {
                        $domainQuery->where('domain', 'like', "%{$query}%");
                    })
                    // Or search in business details
                    ->orWhereHas('businessDetail', function ($businessQuery) use ($query) {
                        $businessQuery->where('business_name', 'like', "%{$query}%")
                            ->orWhere('business_email', 'like', "%{$query}%");
                    });
            })
            ->latest()
            ->paginate($perPage);
    }
}
