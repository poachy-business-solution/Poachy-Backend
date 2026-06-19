<?php

namespace App\Listeners\Tenant;

use App\Events\Tenant\SupplierPaymentRecorded;
use App\Models\Tenant\AuditLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class LogSupplierPaymentRecorded implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(SupplierPaymentRecorded $event): void
    {
        try {
            // AuditLog::create([
            //     'user_id' => $event->payment->created_by,
            //     'user_name' => $event->payment->createdBy->name,
            //     'ip_address' => request()->ip(),
            //     'action' => 'created',
            //     'model_type' => get_class($event->payment),
            //     'model_id' => $event->payment->id,
            //     'old_values' => null,
            //     'new_values' => [
            //         'payment_number' => $event->payment->payment_number,
            //         'supplier_id' => $event->payment->supplier_id,
            //         'supplier_name' => $event->payment->supplier->name,
            //         'purchase_order_id' => $event->payment->purchase_order_id,
            //         'po_number' => $event->payment->purchaseOrder?->po_number,
            //         'payment_date' => $event->payment->payment_date->format('Y-m-d'),
            //         'amount' => $event->payment->amount,
            //         'payment_method' => $event->payment->payment_method->value,
            //         'reference_number' => $event->payment->reference_number,
            //     ],
            //     'description' => sprintf(
            //         'Recorded payment %s for supplier %s (Amount: KES %.2f)',
            //         $event->payment->payment_number,
            //         $event->payment->supplier->name,
            //         $event->payment->amount
            //     ),
            //     'tags' => 'supplier_payment,financial,created',
            // ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit log for supplier payment', [
                'payment_id' => $event->payment->id,
                'error' => $e->getMessage(),
                'tenant_id' => tenant()->id ?? 'system',
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SupplierPaymentRecorded $event, \Throwable $exception): void
    {
        Log::error('Failed to process SupplierPaymentRecorded event', [
            'payment_id' => $event->payment->id,
            'error' => $exception->getMessage(),
            'tenant_id' => tenant()->id ?? 'system',
        ]);
    }
}
