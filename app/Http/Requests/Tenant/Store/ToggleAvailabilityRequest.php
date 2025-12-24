<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;

class ToggleAvailabilityRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'is_available' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'is_available.required' => 'Availability status is required.',
            'is_available.boolean' => 'Availability status must be true or false.',
        ];
    }
}
