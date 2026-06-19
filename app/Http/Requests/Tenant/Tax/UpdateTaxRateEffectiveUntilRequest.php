<?php

namespace App\Http\Requests\Tenant\Tax;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaxRateEffectiveUntilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_until' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'effective_until.date' => 'Effective until must be a valid date',
        ];
    }
}
