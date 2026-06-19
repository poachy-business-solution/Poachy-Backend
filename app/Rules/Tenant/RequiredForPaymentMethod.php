<?php

namespace App\Rules\Tenant;

use App\Enums\Tenant\PaymentMethod;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RequiredForPaymentMethod implements ValidationRule
{
    public function __construct(
        private ?string $paymentMethod
    ) {}

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->paymentMethod) {
            return;
        }

        $method = PaymentMethod::tryFrom($this->paymentMethod);

        if (!$method) {
            return;
        }

        // Check if this payment method requires a reference number
        if ($method->requiresReference() && empty($value)) {
            $fail("Reference number is required for {$method->label()} payments.");
        }
    }
}
