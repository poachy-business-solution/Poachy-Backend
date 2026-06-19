<?php

namespace App\Http\Requests\Tenant\Tax;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax_name' => ['required', 'string', 'max:50'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after:effective_from'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_name.required' => 'Tax name is required',
            'tax_name.max' => 'Tax name cannot exceed 50 characters',
            'rate.required' => 'Tax rate is required',
            'rate.numeric' => 'Tax rate must be a number',
            'rate.min' => 'Tax rate cannot be negative',
            'rate.max' => 'Tax rate cannot exceed 100%',
            'effective_from.required' => 'Effective from date is required',
            'effective_from.date' => 'Effective from must be a valid date',
            'effective_until.date' => 'Effective until must be a valid date',
            'effective_until.after' => 'Effective until must be after effective from date',
        ];
    }
}
