<?php

namespace App\Http\Requests\Public;

use App\Helpers\PhoneNumber;
use App\Services\Central\Billing\BillingTopupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Pay-page STK request. Public (the link is the credential); the controller checks the link.
 */
class PayLinkStkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'integer', 'min:'.(int) ceil(app(BillingTopupService::class)->minimumTopup()), 'max:'.BillingTopupService::MAX_STK_AMOUNT],
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
