<?php

namespace App\Observers\Tenant;

use App\Events\Tenant\SupplierPaymentRecorded;
use App\Models\Tenant\SupplierPayment;
use App\Services\Tenant\AuditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupplierPaymentObserver
{
    public function __construct(
        private AuditService $auditService
    ) {}

    /**
     * Handle the SupplierPayment "creating" event.
     */
    public function creating(SupplierPayment $payment): void
    {
        // Auto-generate payment number if not provided
        if (empty($payment->payment_number)) {
            $payment->payment_number = $this->generatePaymentNumber();
        }

        // Set created_by if not provided
        if (empty($payment->created_by)) {
            $payment->created_by = Auth::id() ?? 1;
        }
    }

    /**
     * Handle the SupplierPayment "created" event.
     */
    public function created(SupplierPayment $payment): void
    {
        // Create audit log
        try {
            $this->auditService->createAudit(
                model: $payment,
                action: 'created',
                oldValues: null,
                newValues: $payment->toArray(),
                description: $this->generateCreationDescription($payment),
                tags: ['supplier_payment', 'financial', 'payment']
            );
        } catch (\Exception $e) {
            Log::error('Failed to create supplier payment audit log', [
                'tenant_id' => tenant()?->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Dispatch event for audit logging and notifications
        event(new SupplierPaymentRecorded($payment));
    }

    /**
     * Handle the SupplierPayment "updating" event.
     */
    public function updating(SupplierPayment $payment): void
    {
        // Store old values for audit
        $payment->storeOldValuesForAudit();
    }

    /**
     * Handle the SupplierPayment "updated" event.
     */
    public function updated(SupplierPayment $payment): void
    {
        // Create audit log for updates
        try {
            if ($this->auditService->hasCriticalChanges($payment)) {
                $oldValues = $payment->getOldValuesForAudit();
                $criticalChanges = $payment->getCriticalChanges();

                $this->auditService->createAudit(
                    model: $payment,
                    action: 'updated',
                    oldValues: array_intersect_key($oldValues, $criticalChanges),
                    newValues: $criticalChanges,
                    description: $this->generateUpdateDescription($payment, $criticalChanges),
                    tags: ['supplier_payment', 'financial', 'payment']
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to create supplier payment update audit log', [
                'tenant_id' => tenant()?->id,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the SupplierPayment "deleting" event.
     */
    public function deleting(SupplierPayment $payment): bool
    {
        throw new \RuntimeException(
            'Supplier payments cannot be deleted. Contact system administrator if reversal is needed.'
        );
    }

    /**
     * Generate unique payment number
     *
     * @return string
     */
    private function generatePaymentNumber(): string
    {
        $prefix = 'PAY-SUP';
        $year = now()->year;

        $lastPayment = SupplierPayment::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPayment ? ((int) substr($lastPayment->payment_number, -4)) + 1 : 1;

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    private function generateCreationDescription(SupplierPayment $payment): string
    {
        $user = Auth::user()?->name ?? 'System';
        $amount = number_format($payment->amount, 2);
        $supplierName = $payment->supplier->name ?? 'Unknown Supplier';

        if ($payment->purchase_order_id) {
            $poNumber = $payment->purchaseOrder->po_number ?? 'Unknown PO';
            return "{$user} recorded payment {$payment->payment_number} of KES {$amount} to {$supplierName} for PO {$poNumber}";
        }

        return "{$user} recorded payment {$payment->payment_number} of KES {$amount} to {$supplierName}";
    }

    private function generateUpdateDescription(SupplierPayment $payment, array $changes): string
    {
        $user = Auth::user()?->name ?? 'System';

        // Amount change (should be rare/restricted)
        if (isset($changes['amount'])) {
            $oldAmount = number_format($payment->getOriginal('amount'), 2);
            $newAmount = number_format($changes['amount'], 2);
            return "{$user} adjusted payment {$payment->payment_number} amount from KES {$oldAmount} to KES {$newAmount}";
        }

        // Payment method change
        if (isset($changes['payment_method'])) {
            $oldMethod = $payment->getOriginal('payment_method');
            $newMethod = $changes['payment_method'];
            return "{$user} changed payment {$payment->payment_number} method from {$oldMethod} to {$newMethod}";
        }

        // Generic update
        $changedFields = implode(', ', array_keys($changes));
        return "{$user} updated payment {$payment->payment_number} ({$changedFields})";
    }
}
