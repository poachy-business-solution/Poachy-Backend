<?php

namespace App\Http\Requests\Internal\Admin;

use App\Services\Central\Billing\BillingTopupService;

class BillingLinkRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'integer', 'min:10', 'max:'.BillingTopupService::MAX_STK_AMOUNT],
            'email' => ['sometimes', 'boolean'],
        ];
    }
}
