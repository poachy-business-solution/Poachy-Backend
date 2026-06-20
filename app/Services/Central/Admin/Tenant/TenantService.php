<?php

namespace App\Services\Central\Admin\Tenant;

use App\Models\BusinessSubscription;
use App\Models\Domain;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Tenant\TenantAccessService;
use Carbon\Carbon;
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
            // Paybill account assignment is handled by the TenantCreated event listener
            // in TenancyServiceProvider — fires for all creation paths.
            $tenant = Tenant::create([
                'id'   => $tenantId,
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
                'tenant_id'             => $tenant->id,
                'domain'                => $data['domain'],
                'mpesa_paybill_account' => $tenant->mpesa_paybill_account,
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


    /**
     * Start a trial period for a tenant.
     */
    public function startTrialPeriod(string $tenantId, string $trialEndsAt): BusinessSubscription
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $trialEndsAt) {
            // Verify tenant exists
            $tenant = Tenant::find($tenantId);
            if (!$tenant) {
                throw new \Exception("Tenant not found with ID: {$tenantId}");
            }

            // Get the Free subscription plan
            $freePlan = SubscriptionPlan::where('slug', 'free')
                ->where('is_active', true)
                ->first();

            if (!$freePlan) {
                throw new \Exception("Free subscription plan not found.");
            }

            // Check if tenant already has an active trial
            $existingTrial = BusinessSubscription::where('tenant_id', $tenantId)
                ->where('is_trial', true)
                ->where('status', 'trial')
                ->where('trial_ends_at', '>=', now())
                ->first();

            if ($existingTrial) {
                throw new \Exception("Tenant already has an active trial period.");
            }

            // Create trial subscription
            $subscription = BusinessSubscription::create([
                'tenant_id' => $tenantId,
                'subscription_plan_id' => $freePlan->id,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'amount_paid' => 0.00,
                'currency' => 'KES',
                'payment_method' => null,
                'payment_reference' => null,
                'payment_date' => null,
                'status' => 'trial',
                'auto_renew' => false,
                'is_trial' => true,
                'trial_ends_at' => Carbon::parse($trialEndsAt)->toDateString(),
            ]);

            // Clear tenant access cache after successful trial creation
            app(TenantAccessService::class)->clearTenantAccessCache($tenantId);

            return $subscription;
        });
    }

    /**
     * Get all subscriptions for a tenant.
     */
    public function getTenantSubscriptions(string $tenantId)
    {
        // Verify tenant exists
        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            throw new \Exception("Tenant not found with ID: {$tenantId}");
        }

        return BusinessSubscription::where('tenant_id', $tenantId)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
