<?php

namespace App\Services\Tenant\Business;

use App\Models\BusinessDetail;
use Illuminate\Support\Facades\DB;
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
    public function approve(int $businessDetailId, ?string $notes = null): BusinessDetail
    {
        return DB::connection('central')->transaction(function () use ($businessDetailId, $notes) {
            $businessDetail = BusinessDetail::on('central')->findOrFail($businessDetailId);

            $businessDetail->update([
                'status' => 'active',
                'onboarded_at' => now(),
            ]);

            // TODO: Send approval notification email to tenant

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
}
