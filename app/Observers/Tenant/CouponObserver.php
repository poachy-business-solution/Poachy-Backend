<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Coupon;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CouponObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the Coupon "created" event.
     */
    public function created(Coupon $coupon): void
    {
        $this->invalidateCache();

        try {
            $this->auditService->createAudit(
                model: $coupon,
                action: 'created',
                oldValues: null,
                newValues: $coupon->toArray(),
                description: $this->generateCreationDescription($coupon),
                tags: ['coupon', 'promotion']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create coupon audit log', [
                'tenant_id' => tenant()?->id,
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Coupon "updated" event.
     */
    public function updated(Coupon $coupon): void
    {
        $this->invalidateCache();

        try {
            // Check if critical fields changed
            $changes = $coupon->getChanges();
            $criticalFields = $coupon->getCriticalFields();
            $criticalChanges = array_intersect_key($changes, array_flip($criticalFields));

            if (!empty($criticalChanges)) {
                $oldValues = $coupon->getOriginal();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($coupon, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['coupon', 'promotion'];
                if (isset($criticalChanges['discount_value'])) {
                    $tags[] = 'discount_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_active'])) {
                    $tags[] = 'status_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['valid_from']) || isset($criticalChanges['valid_until'])) {
                    $tags[] = 'validity_change';
                }

                $this->auditService->createAudit(
                    model: $coupon,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create coupon update audit log', [
                'tenant_id' => tenant()?->id,
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Coupon "deleted" event.
     */
    public function deleted(Coupon $coupon): void
    {
        $this->invalidateCache();

        try {
            $this->auditService->createAudit(
                model: $coupon,
                action: 'deleted',
                oldValues: $coupon->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($coupon),
                tags: ['coupon', 'promotion', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create coupon deletion audit log', [
                'tenant_id' => tenant()?->id,
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Coupon "restored" event.
     */
    public function restored(Coupon $coupon): void
    {
        $this->invalidateCache();

        try {
            $this->auditService->createAudit(
                model: $coupon,
                action: 'restored',
                oldValues: null,
                newValues: $coupon->toArray(),
                description: $this->generateRestorationDescription($coupon),
                tags: ['coupon', 'promotion']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create coupon restoration audit log', [
                'tenant_id' => tenant()?->id,
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Coupon "force deleted" event.
     */
    public function forceDeleted(Coupon $coupon): void
    {
        $this->invalidateCache();

        try {
            $this->auditService->createAudit(
                model: $coupon,
                action: 'force_deleted',
                oldValues: $coupon->toArray(),
                newValues: null,
                description: $this->generateForceDeletionDescription($coupon),
                tags: ['coupon', 'promotion', 'critical', 'permanent']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create coupon force deletion audit log', [
                'tenant_id' => tenant()?->id,
                'coupon_id' => $coupon->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate tenant-specific coupon cache.
     */
    protected function invalidateCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'coupons'])->flush();
    }

    /**
     * Generate description for coupon creation
     */
    private function generateCreationDescription(Coupon $coupon): string
    {
        $user = Auth::user()?->name ?? 'System';
        $discountType = $coupon->discount_type->label();
        $discountValue = $coupon->discount_type->value === 'percentage'
            ? "{$coupon->discount_value}%"
            : "KES " . number_format($coupon->discount_value, 2);
        $validFrom = $coupon->valid_from->format('M d, Y');
        $validUntil = $coupon->valid_until->format('M d, Y');

        return "{$user} created coupon '{$coupon->code}' with {$discountValue} {$discountType} discount (valid {$validFrom} - {$validUntil})";
    }

    /**
     * Generate description for coupon update
     */
    private function generateUpdateDescription(Coupon $coupon, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Code change
        if (isset($changes['code'])) {
            $oldCode = $coupon->getOriginal('code');
            $newCode = $changes['code'];
            return "{$user} changed coupon code from '{$oldCode}' to '{$newCode}'";
        }

        // Discount value change
        if (isset($changes['discount_value'])) {
            $oldValue = $coupon->getOriginal('discount_value');
            $newValue = $changes['discount_value'];

            $discountType = $coupon->discount_type->value === 'percentage' ? '%' : ' KES';
            return "{$user} changed coupon '{$coupon->code}' discount from {$oldValue}{$discountType} to {$newValue}{$discountType}";
        }

        // Valid from date change
        if (isset($changes['valid_from'])) {
            $oldDate = \Carbon\Carbon::parse($coupon->getOriginal('valid_from'))->format('M d, Y');
            $newDate = \Carbon\Carbon::parse($changes['valid_from'])->format('M d, Y');
            return "{$user} changed coupon '{$coupon->code}' start date from {$oldDate} to {$newDate}";
        }

        // Valid until date change
        if (isset($changes['valid_until'])) {
            $oldDate = \Carbon\Carbon::parse($coupon->getOriginal('valid_until'))->format('M d, Y');
            $newDate = \Carbon\Carbon::parse($changes['valid_until'])->format('M d, Y');
            return "{$user} changed coupon '{$coupon->code}' end date from {$oldDate} to {$newDate}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} coupon '{$coupon->code}'";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated coupon '{$coupon->code}' - {$changedFields}";
    }

    /**
     * Generate description for coupon deletion
     */
    private function generateDeletionDescription(Coupon $coupon): string
    {
        $user = Auth::user()?->name ?? 'System';
        $usageInfo = $coupon->usage_count > 0 ? " ({$coupon->usage_count} uses)" : '';

        return "{$user} deleted coupon '{$coupon->code}'{$usageInfo}";
    }

    /**
     * Generate description for coupon restoration
     */
    private function generateRestorationDescription(Coupon $coupon): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored coupon '{$coupon->code}'";
    }

    /**
     * Generate description for coupon force deletion
     */
    private function generateForceDeletionDescription(Coupon $coupon): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} permanently deleted coupon '{$coupon->code}'";
    }
}
