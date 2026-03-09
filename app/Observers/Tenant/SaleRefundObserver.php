<?php

namespace App\Observers\Tenant;

use App\Events\Tenant\Sales\RefundCompleted;
use App\Events\Tenant\Sales\RefundInitiated;
use App\Models\Tenant\SaleRefund;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Log;

class SaleRefundObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    public function created(SaleRefund $refund): void
    {
        try {
            $this->auditService->createAggregatedAudit(
                model: $refund,
                action: 'created',
                aggregatedData: $this->buildAggregatedData($refund),
                description: "Refund {$refund->refund_number} initiated for sale {$refund->originalSale?->sale_number} — {$refund->formatted_amount}",
                tags: ['refund', 'transaction', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create refund audit log', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            event(new RefundInitiated($refund));
        } catch (\Exception $e) {
            Log::error('Failed to dispatch RefundInitiated event', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(SaleRefund $refund): void
    {
        if (!$refund->wasChanged()) {
            return;
        }

        try {
            $changes = $refund->getChanges();
            $description = "Refund {$refund->refund_number} updated";

            if (isset($changes['status'])) {
                $description = "Refund {$refund->refund_number} status changed to {$refund->status->label()}";
            }

            $this->auditService->createAudit(
                model: $refund,
                action: 'updated',
                oldValues: $refund->getOriginal(),
                newValues: $changes,
                description: $description,
                tags: ['refund', 'transaction', 'financial', 'critical']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create refund update audit log', [
                'refund_id' => $refund->id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($refund->wasChanged('status') && $refund->status->value === 'completed') {
            try {
                event(new RefundCompleted($refund));
            } catch (\Exception $e) {
                Log::error('Failed to dispatch RefundCompleted event', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function buildAggregatedData(SaleRefund $refund): array
    {
        return [
            'parent' => $refund->toArray(),
            'items' => $refund->items->toArray(),
        ];
    }
}
