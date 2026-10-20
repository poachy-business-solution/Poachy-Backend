<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** New payout fees from a date on (today or later, Nairobi). */
class ScheduleMarketplaceFeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // internal.admin middleware checks the role
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'mpesa_fee_percent' => ['required', 'numeric', 'min:0', 'max:20'],
            'card_fee_percent' => ['required', 'numeric', 'min:0', 'max:20'],
            'transfer_fee' => ['required', 'numeric', 'min:0', 'max:1000'],
            'hold_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'min_payout' => ['required', 'integer', 'min:1', 'max:100000'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Africa/Nairobi')->toDateString()],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
