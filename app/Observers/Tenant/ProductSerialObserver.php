<?php

namespace App\Observers\Tenant;

use App\Enums\Tenant\SerialStatus;
use App\Models\Tenant\ProductSerial;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProductSerialObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function created(ProductSerial $serial): void
    {
        $this->clearCache($serial);

        try {
            $this->auditService->createAudit(
                model: $serial,
                action: 'created',
                oldValues: null,
                newValues: $serial->toArray(),
                description: $this->generateCreationDescription($serial),
                tags: ['serial', 'inventory']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product serial audit log', [
                'tenant_id' => tenant()?->id,
                'serial_id' => $serial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updating(ProductSerial $serial): void
    {
        $serial->storeOldValuesForAudit();
    }

    public function updated(ProductSerial $serial): void
    {
        $this->clearCache($serial);

        if (! $serial->wasChanged('status')) {
            return;
        }

        try {
            $oldValues = $serial->getOldValuesForAudit();

            $this->auditService->createAudit(
                model: $serial,
                action: 'updated',
                oldValues: ['status' => $oldValues['status'] ?? null],
                newValues: ['status' => $serial->status->value],
                description: $this->generateUpdateDescription($serial),
                tags: ['serial', 'inventory', 'status_change']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product serial update audit log', [
                'tenant_id' => tenant()?->id,
                'serial_id' => $serial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(ProductSerial $serial): void
    {
        $this->clearCache($serial);

        try {
            $this->auditService->createAudit(
                model: $serial,
                action: 'deleted',
                oldValues: $serial->toArray(),
                newValues: null,
                description: $this->generateDeletionDescription($serial),
                tags: ['serial', 'inventory', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create product serial deletion audit log', [
                'tenant_id' => tenant()?->id,
                'serial_id' => $serial->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function clearCache(ProductSerial $serial): void
    {
        Cache::tags(['tenant', tenant()->id, 'serials'])->flush();
        Cache::tags(['tenant', tenant()->id, 'inventory'])->flush();

        if ($serial->product_id) {
            Cache::tags(['tenant', tenant()->id, 'products'])->flush();
        }
    }

    private function generateCreationDescription(ProductSerial $serial): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $serial->product?->name ?? 'Unknown Product';

        return "{$user} received serial {$serial->serial_number} for {$productName}";
    }

    private function generateUpdateDescription(ProductSerial $serial): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $serial->product?->name ?? 'Unknown Product';
        $oldStatus = $serial->getOldValuesForAudit()['status'] ?? null;
        $oldStatusValue = $oldStatus instanceof SerialStatus ? $oldStatus->value : $oldStatus;

        return "{$user} changed serial {$serial->serial_number} ({$productName}) status from {$oldStatusValue} to {$serial->status->value}";
    }

    private function generateDeletionDescription(ProductSerial $serial): string
    {
        $user = Auth::user()?->name ?? 'System';
        $productName = $serial->product?->name ?? 'Unknown Product';

        return "{$user} deleted serial {$serial->serial_number} ({$productName})";
    }
}
