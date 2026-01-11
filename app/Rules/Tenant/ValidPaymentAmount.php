<?php

namespace App\Rules\Tenant;

use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPaymentAmount implements ValidationRule
{
    public function __construct(
        private ?int $supplierId,
        private ?int $purchaseOrderId
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->supplierId || !$value) {
            return;
        }

        $supplier = Supplier::find($this->supplierId);

        if (!$supplier) {
            $fail('The selected supplier does not exist.');
            return;
        }

        // Check supplier outstanding balance
        if ($value > $supplier->outstanding_balance) {
            $fail(sprintf(
                'Payment amount (KES %.2f) exceeds supplier outstanding balance (KES %.2f).',
                $value,
                $supplier->outstanding_balance
            ));
            return;
        }

        // If PO linked, check PO outstanding
        if ($this->purchaseOrderId) {
            $po = PurchaseOrder::find($this->purchaseOrderId);

            if (!$po) {
                $fail('The selected purchase order does not exist.');
                return;
            }

            $poOutstanding = $po->total_amount - $po->amount_paid;

            if ($value > $poOutstanding) {
                $fail(sprintf(
                    'Payment amount (KES %.2f) exceeds purchase order outstanding balance (KES %.2f).',
                    $value,
                    $poOutstanding
                ));
            }
        }
    }
}
