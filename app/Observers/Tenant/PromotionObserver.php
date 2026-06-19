<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Promotion;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromotionObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the Promotion "creating" event
     */
    public function creating(Promotion $promotion): void
    {
        // Auto-generate code if not provided
        if (empty($promotion->code)) {
            $promotion->code = $this->generateUniqueCode();
        }

        // Ensure code is uppercase
        $promotion->code = strtoupper($promotion->code);
    }

    /**
     * Handle the Promotion "created" event
     */
    public function created(Promotion $promotion): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $promotion,
                action: 'created',
                oldValues: null,
                newValues: $promotion->toArray(),
                description: $this->generateCreationDescription($promotion),
                tags: ['promotion', 'marketing']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create promotion audit log', [
                'tenant_id' => tenant()?->id,
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Promotion "updating" event
     */
    public function updating(Promotion $promotion): void
    {
        // Ensure code remains uppercase
        if ($promotion->isDirty('code')) {
            $promotion->code = strtoupper($promotion->code);
        }
    }

    /**
     * Handle the Promotion "updated" event
     */
    public function updated(Promotion $promotion): void
    {
        $this->clearCache();

        try {
            // Check if critical fields changed
            $changes = $promotion->getChanges();
            $criticalFields = $promotion->getCriticalFields();
            $criticalChanges = array_intersect_key($changes, array_flip($criticalFields));

            if (!empty($criticalChanges)) {
                $oldValues = $promotion->getOriginal();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($promotion, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['promotion', 'marketing'];
                if (isset($criticalChanges['discount_value'])) {
                    $tags[] = 'discount_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_active'])) {
                    $tags[] = 'status_change';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['start_date']) || isset($criticalChanges['end_date'])) {
                    $tags[] = 'schedule_change';
                }

                $this->auditService->createAudit(
                    model: $promotion,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create promotion update audit log', [
                'tenant_id' => tenant()?->id,
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Promotion "deleted" event
     */
    public function deleted(Promotion $promotion): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $promotion,
                action: 'deleted',
                oldValues: $promotion->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($promotion),
                tags: ['promotion', 'marketing', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create promotion deletion audit log', [
                'tenant_id' => tenant()?->id,
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Promotion "restored" event
     */
    public function restored(Promotion $promotion): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $promotion,
                action: 'restored',
                oldValues: null,
                newValues: $promotion->toArray(),
                description: $this->generateRestorationDescription($promotion),
                tags: ['promotion', 'marketing']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create promotion restoration audit log', [
                'tenant_id' => tenant()?->id,
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::info('Promotion restored via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Handle the Promotion "force deleted" event
     */
    public function forceDeleted(Promotion $promotion): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $promotion,
                action: 'force_deleted',
                oldValues: $promotion->toArray(),
                newValues: null,
                description: $this->generateForceDeletionDescription($promotion),
                tags: ['promotion', 'marketing', 'critical', 'permanent']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create promotion force deletion audit log', [
                'tenant_id' => tenant()?->id,
                'promotion_id' => $promotion->id,
                'error' => $e->getMessage(),
            ]);
        }

        Log::warning('Promotion force deleted via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Generate a unique promotion code
     */
    protected function generateUniqueCode(): string
    {
        do {
            $code = 'PROMO-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (Promotion::where('code', $code)->exists());

        return $code;
    }

    /**
     * Clear all promotion-related cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'promotions'])->flush();
    }

    /**
     * Generate description for promotion creation
     */
    private function generateCreationDescription(Promotion $promotion): string
    {
        $user = Auth::user()?->name ?? 'System';
        $promotionType = $promotion->promotion_type->label();
        $startDate = $promotion->start_date->format('M d, Y');
        $endDate = $promotion->end_date->format('M d, Y');

        return "{$user} created promotion '{$promotion->name}' ({$promotion->code}) - {$promotionType} (active {$startDate} - {$endDate})";
    }

    /**
     * Generate description for promotion update
     */
    private function generateUpdateDescription(Promotion $promotion, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Name change
        if (isset($changes['name'])) {
            $oldName = $promotion->getOriginal('name');
            $newName = $changes['name'];
            return "{$user} changed promotion name from '{$oldName}' to '{$newName}' ({$promotion->code})";
        }

        // Discount value change
        if (isset($changes['discount_value'])) {
            $oldValue = $promotion->getOriginal('discount_value');
            $newValue = $changes['discount_value'];
            $promotionType = $promotion->promotion_type->label();

            return "{$user} changed promotion '{$promotion->name}' discount from {$oldValue} to {$newValue} ({$promotionType})";
        }

        // Start date change
        if (isset($changes['start_date'])) {
            $oldDate = \Carbon\Carbon::parse($promotion->getOriginal('start_date'))->format('M d, Y');
            $newDate = \Carbon\Carbon::parse($changes['start_date'])->format('M d, Y');
            return "{$user} changed promotion '{$promotion->name}' start date from {$oldDate} to {$newDate}";
        }

        // End date change
        if (isset($changes['end_date'])) {
            $oldDate = \Carbon\Carbon::parse($promotion->getOriginal('end_date'))->format('M d, Y');
            $newDate = \Carbon\Carbon::parse($changes['end_date'])->format('M d, Y');
            return "{$user} changed promotion '{$promotion->name}' end date from {$oldDate} to {$newDate}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} promotion '{$promotion->name}' ({$promotion->code})";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated promotion '{$promotion->name}' ({$promotion->code}) - {$changedFields}";
    }

    /**
     * Generate description for promotion deletion
     */
    private function generateDeletionDescription(Promotion $promotion): string
    {
        $user = Auth::user()?->name ?? 'System';
        $usageInfo = $promotion->total_usage_count > 0 ? " ({$promotion->total_usage_count} uses)" : '';

        return "{$user} deleted promotion '{$promotion->name}' ({$promotion->code}){$usageInfo}";
    }

    /**
     * Generate description for promotion restoration
     */
    private function generateRestorationDescription(Promotion $promotion): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored promotion '{$promotion->name}' ({$promotion->code})";
    }

    /**
     * Generate description for promotion force deletion
     */
    private function generateForceDeletionDescription(Promotion $promotion): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} permanently deleted promotion '{$promotion->name}' ({$promotion->code})";
    }
}
