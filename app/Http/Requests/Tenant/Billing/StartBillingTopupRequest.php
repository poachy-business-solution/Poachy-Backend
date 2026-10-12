<?php

namespace App\Http\Requests\Tenant\Billing;

use App\Helpers\PhoneNumber;
use App\Services\Central\Billing\BillingTopupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Owner starts an M-Pesa prompt to top up the shop's Poachy balance (web business account).
 * Requires an Idempotency-Key header so a double submit never prompts twice.
 */
class StartBillingTopupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:'.(int) ceil(app(BillingTopupService::class)->minimumTopup()), 'max:'.BillingTopupService::MAX_STK_AMOUNT],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $key = (string) $this->header('Idempotency-Key');
            if ($key === '' || strlen($key) > 100) {
                $validator->errors()->add('idempotency_key', 'Send an Idempotency-Key header (1–100 characters).');
            }
            if ($this->filled('phone') && ! PhoneNumber::isValid((string) $this->input('phone'))) {
                $validator->errors()->add('phone', 'Enter a Kenyan mobile number.');
            }
        }];
    }
}
