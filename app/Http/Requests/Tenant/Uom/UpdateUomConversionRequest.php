<?php

namespace App\Http\Requests\Tenant\Uom;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUomConversionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'conversion_factor' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,6})?$/', // Max 6 decimal places
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'conversion_factor.required' => 'Conversion factor is required.',
            'conversion_factor.gt' => 'Conversion factor must be greater than zero.',
            'conversion_factor.regex' => 'Conversion factor can have maximum 6 decimal places.',
        ];
    }
}
