<?php

namespace App\Observers\Tenant;

use App\Events\Tenant\SupplierPaymentRecorded;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SupplierPaymentObserver
{
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
        // Dispatch event for audit logging and notifications
        event(new SupplierPaymentRecorded($payment));
    }

    /**
     * Handle the SupplierPayment "updated" event.
     */
    public function updated(SupplierPayment $payment): void {}

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
}
