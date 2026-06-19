<?php

namespace App\Rules\Tenant;

use App\Models\Tenant\Supplier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupplierIsActive implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value) {
            return;
        }

        $supplier = Supplier::find($value);

        if (!$supplier) {
            $fail('The selected supplier does not exist.');
            return;
        }

        if (!$supplier->is_active) {
            $fail('The selected supplier is not active. Only active suppliers can receive payments.');
        }
    }
}
