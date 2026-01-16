<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Supplier;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SupplierObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function creating(Supplier $supplier): void {}

    public function created(Supplier $supplier): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $supplier,
                action: 'created',
                oldValues: null,
                newValues: $supplier->toArray(),
                description: $this->generateCreationDescription($supplier),
                tags: ['supplier', 'profile']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create supplier audit log', [
                'tenant_id' => tenant()?->id,
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updating(Supplier $supplier): void
    {
        $changes = $supplier->getDirty();

        if (!empty($changes)) {
            Log::info('Updating supplier', [
                'tenant_id' => tenant()->id,
                'supplier_id' => $supplier->id,
                'changes' => $changes,
            ]);
        }
    }

    public function updated(Supplier $supplier): void
    {
        $this->clearCache();

        try {
            // Check if critical fields changed
            $changes = $supplier->getChanges();
            $criticalFields = $supplier->getCriticalFields();
            $criticalChanges = array_intersect_key($changes, array_flip($criticalFields));

            if (!empty($criticalChanges)) {
                $oldValues = $supplier->getOriginal();

                // Generate context-aware description
                $description = $this->generateUpdateDescription($supplier, $criticalChanges);

                // Add specific tags based on changes
                $tags = ['supplier', 'profile'];
                if (isset($criticalChanges['credit_limit']) || isset($criticalChanges['outstanding_balance'])) {
                    $tags[] = 'credit';
                    $tags[] = 'financial';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['payment_terms'])) {
                    $tags[] = 'payment_terms';
                    $tags[] = 'critical';
                }
                if (isset($criticalChanges['is_active'])) {
                    $tags[] = 'status_change';
                    $tags[] = 'critical';
                }

                $this->auditService->createAudit(
                    model: $supplier,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $description,
                    tags: $tags
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create supplier update audit log', [
                'tenant_id' => tenant()?->id,
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleting(Supplier $supplier): void {}

    public function deleted(Supplier $supplier): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $supplier,
                action: 'deleted',
                oldValues: $supplier->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($supplier),
                tags: ['supplier', 'profile', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create supplier deletion audit log', [
                'tenant_id' => tenant()?->id,
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Supplier "restored" event.
     */
    public function restored(Supplier $supplier): void
    {
        $this->clearCache();

        try {
            $this->auditService->createAudit(
                model: $supplier,
                action: 'restored',
                oldValues: null,
                newValues: $supplier->toArray(),
                description: $this->generateRestorationDescription($supplier),
                tags: ['supplier', 'profile']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create supplier restoration audit log', [
                'tenant_id' => tenant()?->id,
                'supplier_id' => $supplier->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'suppliers'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear supplier cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate description for supplier creation
     */
    private function generateCreationDescription(Supplier $supplier): string
    {
        $user = Auth::user()?->name ?? 'System';
        $supplierType = $supplier->supplier_type->displayName();

        return "{$user} created supplier {$supplier->name} as {$supplierType}";
    }

    /**
     * Generate description for supplier update
     */
    private function generateUpdateDescription(Supplier $supplier, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Name change
        if (isset($changes['name'])) {
            $oldName = $supplier->getOriginal('name');
            $newName = $changes['name'];
            return "{$user} changed supplier name from {$oldName} to {$newName}";
        }

        // Email change
        if (isset($changes['email'])) {
            $oldEmail = $supplier->getOriginal('email');
            $newEmail = $changes['email'];
            return "{$user} changed supplier {$supplier->name} email from {$oldEmail} to {$newEmail}";
        }

        // Phone change
        if (isset($changes['phone'])) {
            $oldPhone = $supplier->getOriginal('phone');
            $newPhone = $changes['phone'];
            return "{$user} changed supplier {$supplier->name} phone from {$oldPhone} to {$newPhone}";
        }

        // Credit limit change
        if (isset($changes['credit_limit'])) {
            $oldLimit = number_format($supplier->getOriginal('credit_limit'), 2);
            $newLimit = number_format($changes['credit_limit'], 2);
            return "{$user} changed credit limit for {$supplier->name} from KES {$oldLimit} to KES {$newLimit}";
        }

        // Outstanding balance change
        if (isset($changes['outstanding_balance'])) {
            $oldBalance = number_format($supplier->getOriginal('outstanding_balance'), 2);
            $newBalance = number_format($changes['outstanding_balance'], 2);
            return "{$user} updated outstanding balance for {$supplier->name} from KES {$oldBalance} to KES {$newBalance}";
        }

        // Payment terms change
        if (isset($changes['payment_terms'])) {
            $oldTerms = $supplier->getOriginal('payment_terms');
            $newTerms = $changes['payment_terms'];
            return "{$user} changed payment terms for {$supplier->name} from {$oldTerms} to {$newTerms}";
        }

        // Active status change
        if (isset($changes['is_active'])) {
            $status = $changes['is_active'] ? 'activated' : 'deactivated';
            return "{$user} {$status} supplier {$supplier->name}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated supplier {$supplier->name} - {$changedFields}";
    }

    /**
     * Generate description for supplier deletion
     */
    private function generateDeletionDescription(Supplier $supplier): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productCount = $supplier->products()->count();
        $productInfo = $productCount > 0 ? " ({$productCount} products)" : '';

        return "{$user} deleted supplier {$supplier->name}{$productInfo}";
    }

    /**
     * Generate description for supplier restoration
     */
    private function generateRestorationDescription(Supplier $supplier): string
    {
        $user = Auth::user()?->name ?? 'System';

        return "{$user} restored supplier {$supplier->name}";
    }
}
