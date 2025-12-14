<?php

namespace App\Services\Tenant\Business;

use App\Mail\Central\Business\BusinessApprovedMail;
use App\Mail\Central\Business\BusinessVerificationMail;
use App\Models\BusinessDetail;
use App\Models\Domain;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Services\Tenant\TenantAccessService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BusinessDetailsService
{
    /**
     * Submit business details (tenant creates, stored in central DB).
     * 
     */
    public function submitBusinessDetails(string $tenantId, array $data): BusinessDetail
    {
        // Check if business details already exist
        if (BusinessDetail::on('central')->where('tenant_id', $tenantId)->exists()) {
            throw new \Exception('Business details already submitted. Please contact admin for updates.');
        }

        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            // Handle file uploads
            $logoPath = null;
            $bannerPath = null;

            if (isset($data['business_logo'])) {
                $logoPath = $data['business_logo']->store('business/logos', 'public');
            }

            if (isset($data['business_banner'])) {
                $bannerPath = $data['business_banner']->store('business/banners', 'public');
            }

            // Create business details
            $businessDetail = BusinessDetail::on('central')->create([
                'tenant_id' => $tenantId,
                'business_name' => $data['business_name'],
                'business_description' => $data['business_description'] ?? null,
                'business_logo' => $logoPath,
                'business_banner' => $bannerPath,
                'business_type_id' => $data['business_type_id'],
                'business_category_id' => $data['business_category_id'],
                'business_email' => $data['business_email'] ?? null,
                'business_phone' => $data['business_phone'],
                'contact_person' => $data['contact_person'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'county' => $data['county'] ?? null,
                'operating_hours' => $data['operating_hours'] ?? null,
                'delivery_info' => $data['delivery_info'] ?? null,
                'settings' => $data['settings'] ?? null,
                'social_media' => $data['social_media'] ?? null,
                'status' => 'pending', // Always pending on submission
            ]);

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Get business details for tenant.
     */
    public function getBusinessDetails(string $tenantId): ?BusinessDetail
    {
        return BusinessDetail::on('central')
            ->with(['businessType', 'businessCategory'])
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Approve business details (admin action).
     */
    public function approve(int $businessDetailId): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($businessDetailId) {
            $businessDetail = BusinessDetail::on('central')->findOrFail($businessDetailId);

            $businessDetail->activate();

            // Send approval notification email to tenant owner
            $this->sendApprovalEmail($businessDetail);

            // Clear tenant access cache
            app(TenantAccessService::class)->clearTenantAccessCache($businessDetail->tenant_id);

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Reject business details (admin action).
     */
    public function reject(int $businessDetailId, ?string $notes = null): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($businessDetailId, $notes) {
            $businessDetail = BusinessDetail::on('central')->findOrFail($businessDetailId);

            // Delete uploaded files if rejecting
            if ($businessDetail->business_logo) {
                Storage::disk('public')->delete($businessDetail->business_logo);
            }
            if ($businessDetail->business_banner) {
                Storage::disk('public')->delete($businessDetail->business_banner);
            }

            // Delete the business details record
            $businessDetail->delete();

            // TODO: Send rejection notification email to tenant with notes

            return $businessDetail;
        });
    }

    /**
     * Verify business (add verification badge).
     */
    public function verify(int $businessDetailId, bool $isVerified): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($businessDetailId, $isVerified) {
            $businessDetail = BusinessDetail::on('central')->findOrFail($businessDetailId);

            $businessDetail->verify();

            $this->sendVerificationEmail($businessDetail, $isVerified);

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Get all pending business details (admin view).
     */
    public function getPending(int $perPage = 15)
    {
        return BusinessDetail::on('central')
            ->with(['businessType', 'businessCategory', 'tenant.domains'])
            ->where('status', 'pending')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get all business details with filters (admin view).
     */
    public function getAllBusinessDetails(array $filters = [], int $perPage = 15)
    {
        $query = BusinessDetail::on('central')
            ->with(['businessType', 'businessCategory', 'tenant.domains']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['business_type_id'])) {
            $query->where('business_type_id', $filters['business_type_id']);
        }

        if (isset($filters['is_verified'])) {
            $query->where('is_verified', $filters['is_verified']);
        }

        if (isset($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        return $query->latest()->paginate($perPage);
    }

    /**
     * Update business profile (name, description, email, phone, contact person).
     */
    public function updateProfile(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $businessDetail->update([
                'business_name' => $data['business_name'] ?? $businessDetail->business_name,
                'business_description' => $data['business_description'] ?? $businessDetail->business_description,
                'business_email' => $data['business_email'] ?? $businessDetail->business_email,
                'business_phone' => $data['business_phone'] ?? $businessDetail->business_phone,
                'contact_person' => $data['contact_person'] ?? $businessDetail->contact_person,
            ]);

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update business media (logo and banner).
     */
    public function updateMedia(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $updateData = [];

            // Handle logo upload
            if (isset($data['business_logo'])) {
                // Delete old logo
                if ($businessDetail->business_logo) {
                    Storage::disk('public')->delete($businessDetail->business_logo);
                }
                $updateData['business_logo'] = $data['business_logo']->store('business/logos', 'public');
            }

            // Handle banner upload
            if (isset($data['business_banner'])) {
                // Delete old banner
                if ($businessDetail->business_banner) {
                    Storage::disk('public')->delete($businessDetail->business_banner);
                }
                $updateData['business_banner'] = $data['business_banner']->store('business/banners', 'public');
            }

            if (!empty($updateData)) {
                $businessDetail->update($updateData);
            }

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update business location (address, city, county).
     */
    public function updateLocation(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            $businessDetail->update([
                'address' => $data['address'] ?? $businessDetail->address,
                'city' => $data['city'] ?? $businessDetail->city,
                'county' => $data['county'] ?? $businessDetail->county,
            ]);

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update operating hours.
     */
    public function updateOperatingHours(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Only update if operating_hours is provided
            if (isset($data['operating_hours'])) {
                $existingOperatingHours = $businessDetail->operating_hours ?? [];
                $updatedOperatingHours = array_merge($existingOperatingHours, $data['operating_hours']);

                $businessDetail->update([
                    'operating_hours' => $updatedOperatingHours,
                ]);
            }

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update delivery information.
     */
    public function updateDeliveryInfo(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Only update if delivery_info is provided
            if (isset($data['delivery_info'])) {
                $existingDeliveryInfo = $businessDetail->delivery_info ?? [];
                $updatedDeliveryInfo = array_merge($existingDeliveryInfo, $data['delivery_info']);

                $businessDetail->update([
                    'delivery_info' => $updatedDeliveryInfo,
                ]);
            }

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update business settings.
     */
    public function updateSettings(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Only update if settings is provided
            if (isset($data['settings'])) {
                $existingSettings = $businessDetail->settings ?? [];
                $updatedSettings = array_merge($existingSettings, $data['settings']);

                $businessDetail->update([
                    'settings' => $updatedSettings,
                ]);
            }

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Update social media links.
     */
    public function updateSocialMedia(string $tenantId, array $data): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($tenantId, $data) {
            $businessDetail = BusinessDetail::on('central')
                ->where('tenant_id', $tenantId)
                ->firstOrFail();

            // Only update if social_media is provided
            if (isset($data['social_media'])) {
                $existingSocialMedia = $businessDetail->social_media ?? [];
                $updatedSocialMedia = array_merge($existingSocialMedia, $data['social_media']);

                $businessDetail->update([
                    'social_media' => $updatedSocialMedia,
                ]);
            }

            return $businessDetail->fresh(['businessType', 'businessCategory']);
        });
    }

    /**
     * Send approval email to business owner.
     */
    private function sendApprovalEmail(BusinessDetail $businessDetail): void
    {
        try {
            // Get tenant
            $tenant = Tenant::find($businessDetail->tenant_id);

            if (!$tenant) {
                Log::warning('Tenant not found for approved business', [
                    'business_id' => $businessDetail->id,
                    'tenant_id' => $businessDetail->tenant_id,
                ]);
                return;
            }

            // Initialize tenancy to query tenant database
            tenancy()->initialize($tenant);

            // Get owner user from tenant database
            $owner = TenantUser::whereHas('roles', function ($query) {
                $query->where('name', 'owner');
            })->first();

            // End tenancy context
            tenancy()->end();

            if (!$owner) {
                Log::warning('Owner not found for approved business', [
                    'business_id' => $businessDetail->id,
                    'tenant_id' => $businessDetail->tenant_id,
                ]);
                return;
            }

            // Get primary domain for login URL
            $primaryDomain = Domain::on('central')
                ->where('tenant_id', $tenant->id)
                ->orderBy('id', 'asc')
                ->first();

            $loginUrl = $primaryDomain
                ? 'https://' . $primaryDomain->domain . '/login'
                : config('app.url') . '/login';

            // Get available subscription plans
            $subscriptionPlans = SubscriptionPlan::on('central')
                ->where('is_active', true)
                ->orderBy('price', 'asc')
                ->get()
                ->map(function ($plan) {
                    return [
                        'name' => $plan->name,
                        'price' => $plan->price,
                        'description' => $plan->description,
                        'key_features' => $this->extractKeyFeatures($plan->features ?? []),
                    ];
                })
                ->toArray();

            // Send email
            Mail::to($owner->email)->queue(
                new BusinessApprovedMail(
                    ownerName: $owner->name,
                    businessName: $businessDetail->business_name,
                    loginUrl: $loginUrl,
                    subscriptionPlans: $subscriptionPlans
                )
            );

            Log::info('Business approval email sent', [
                'business_id' => $businessDetail->id,
                'owner_email' => $owner->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send business approval email', [
                'business_id' => $businessDetail->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send verification email to business owner
     */
    private function sendVerificationEmail(BusinessDetail $businessDetail, bool $isVerified): void
    {
        try {
            // Get tenant
            $tenant = Tenant::find($businessDetail->tenant_id);

            if (!$tenant) {
                Log::warning('Tenant not found for approved business', [
                    'business_id' => $businessDetail->id,
                    'tenant_id' => $businessDetail->tenant_id,
                ]);
                return;
            }

            // Initialize tenancy to query tenant database
            tenancy()->initialize($tenant);

            // Get owner user from tenant database
            $owner = TenantUser::whereHas('roles', function ($query) {
                $query->where('name', 'owner');
            })->first();

            // End tenancy context
            tenancy()->end();

            if (!$owner) {
                Log::warning('Owner not found for approved business', [
                    'business_id' => $businessDetail->id,
                    'tenant_id' => $businessDetail->tenant_id,
                ]);
                return;
            }

            Mail::to($owner->email)->queue(
                new BusinessVerificationMail(
                    ownerName: $owner->name,
                    businessName: $businessDetail->business_name,
                    isVerified: $isVerified,
                )
            );

            Log::info('Verification email sent', [
                'business_id' => $businessDetail->id,
                'owner_email' => $owner->email,
                'is_verified' => $isVerified,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send verification email', [
                'business_id' => $businessDetail->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract key features from plan features array.
     */
    private function extractKeyFeatures(array $features): array
    {
        $keyFeatures = [];

        if (isset($features['max_products'])) {
            $keyFeatures[] = is_numeric($features['max_products'])
                ? "Up to {$features['max_products']} products"
                : "Unlimited products";
        }

        if (isset($features['max_users'])) {
            $keyFeatures[] = is_numeric($features['max_users'])
                ? "Up to {$features['max_users']} users"
                : "Unlimited users";
        }

        if (isset($features['max_locations'])) {
            $keyFeatures[] = is_numeric($features['max_locations'])
                ? "Up to {$features['max_locations']} locations"
                : "Unlimited locations";
        }

        if (!empty($features['enable_ecommerce'])) {
            $keyFeatures[] = "eCommerce enabled";
        }

        if (!empty($features['enable_marketplace'])) {
            $keyFeatures[] = "Marketplace access";
        }

        if (!empty($features['enable_analytics'])) {
            $level = is_string($features['enable_analytics']) ? $features['enable_analytics'] : '';
            $keyFeatures[] = "Analytics" . ($level ? " ({$level})" : "");
        }

        return array_slice($keyFeatures, 0, 5); // Top 5 features
    }
}
