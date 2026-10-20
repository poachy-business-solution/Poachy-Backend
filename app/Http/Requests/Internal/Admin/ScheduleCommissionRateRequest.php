<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A new marketplace commission rate from a date on (own-link orders always stay at 0%). */
class ScheduleCommissionRateRequest extends FormRequest
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
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'effective_from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now('Africa/Nairobi')->toDateString()],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
