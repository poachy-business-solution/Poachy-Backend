<?php

namespace App\Http\Requests\Internal\Admin;

use App\Helpers\PhoneNumber;
use App\Services\Central\Billing\BillingTopupService;

class BillingStkRequest extends InternalAdminRequest
{
    protected bool $needsIdempotencyKey = true;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:'.(int) ceil(app(BillingTopupService::class)->minimumTopup()), 'max:'.BillingTopupService::MAX_STK_AMOUNT],
            // Defaults to the shop's business phone.
            'phone' => ['nullable', 'string', 'max:20', function ($attr, $value, $fail) {
                if ($value !== null && (! is_string($value) || ! PhoneNumber::isValid($value))) {
                    $fail('Enter a Kenyan mobile number.');
                }
            }],
        ];
    }
}
