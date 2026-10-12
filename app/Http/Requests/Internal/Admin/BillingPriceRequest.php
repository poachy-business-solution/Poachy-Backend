<?php

namespace App\Http\Requests\Internal\Admin;

use Carbon\CarbonImmutable;

/** A new price row. Prices only change from a future Nairobi date, never retroactively. */
class BillingPriceRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'effective_from' => ['required', 'date_format:Y-m-d', 'after:'.CarbonImmutable::now('Africa/Nairobi')->toDateString()],
            'first_branch_day' => ['required', 'numeric', 'min:0', 'max:10000'],
            'extra_branch_day' => ['required', 'numeric', 'min:0', 'max:10000'],
            'first_branch_cap' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'extra_branch_cap' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'min_topup' => ['required', 'numeric', 'min:1', 'max:10000'],
            'low_balance_days' => ['required', 'integer', 'min:0', 'max:60'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
