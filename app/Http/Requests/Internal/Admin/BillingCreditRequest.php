<?php

namespace App\Http\Requests\Internal\Admin;

class BillingCreditRequest extends InternalAdminRequest
{
    protected bool $needsIdempotencyKey = true;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:'.(int) config('internal_admin.max_credit')],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
