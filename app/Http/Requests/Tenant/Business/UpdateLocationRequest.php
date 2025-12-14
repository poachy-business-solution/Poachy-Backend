<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => ['sometimes', 'string', 'max:500'],
            'city' => ['sometimes', 'string', 'max:100'],
            'county' => ['sometimes', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            // 'address.required' => 'Business address is required.',
            'address.max' => 'Address cannot exceed 500 characters.',
            // 'city.required' => 'City is required.',
            'city.max' => 'City name cannot exceed 100 characters.',
            // 'county.required' => 'County is required.',
            'county.max' => 'County name cannot exceed 100 characters.',
        ];
    }
}
