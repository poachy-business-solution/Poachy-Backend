<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'business_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'], // 2MB max
            'business_banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'], // 5MB max
        ];
    }

    public function messages(): array
    {
        return [
            'business_logo.mimes' => 'Logo must be a JPG, JPEG, or PNG image.',
            'business_logo.max' => 'Logo size cannot exceed 2MB.',
            'business_banner.mimes' => 'Banner must be a JPG, JPEG, or PNG image.',
            'business_banner.max' => 'Banner size cannot exceed 5MB.',
        ];
    }
}
