<?php

namespace App\Rules\Tenant;

use App\Models\Tenant\PurchaseOrder;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PurchaseOrderBelongsToSupplier implements ValidationRule
{
    public function __construct(
        private ?int $supplierId
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip validation if either value is null (handled by nullable rule)
        if (!$value || !$this->supplierId) {
            return;
        }

        $po = PurchaseOrder::find($value);

        if (!$po) {
            $fail('The selected purchase order does not exist.');
            return;
        }

        if ($po->supplier_id !== $this->supplierId) {
            $fail('The selected purchase order does not belong to this supplier.');
        }
    }
}
