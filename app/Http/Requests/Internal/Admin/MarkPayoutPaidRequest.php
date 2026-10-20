<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
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
            'mpesa_receipt' => ['required', 'string', 'regex:/^[A-Z0-9]{8,12}$/'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['mpesa_receipt.regex' => 'Enter the M-Pesa receipt as it appears on the statement (8–12 letters and numbers).'];
    }
}
