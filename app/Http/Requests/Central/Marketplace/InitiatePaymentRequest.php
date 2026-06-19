<?php

namespace App\Http\Requests\Central\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone_number.max' => 'Phone number must not exceed 20 characters.',
        ];
    }
}
