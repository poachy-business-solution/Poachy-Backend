<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitBusinessDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Tenant must be authenticated
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Required fields
            'business_name' => ['required', 'string', 'max:255'],
            'business_type_id' => ['required', 'integer', 'exists:central.business_types,id'],
            'business_category_id' => ['required', 'integer', 'exists:central.business_categories,id'],
            'business_phone' => ['required', 'string', 'max:20'],

            // Optional text fields
            'business_description' => ['nullable', 'string', 'max:1000'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:100'],

            // File uploads
            'business_logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'], // 2MB
            'business_banner' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'], // 5MB

            // JSON fields
            'operating_hours' => ['nullable', 'array'],
            'operating_hours.*.open' => ['required_with:operating_hours', 'date_format:H:i'],
            'operating_hours.*.close' => ['required_with:operating_hours', 'date_format:H:i'],

            'delivery_info' => ['nullable', 'array'],
            'delivery_info.available' => ['sometimes', 'boolean'],
            'delivery_info.areas' => ['sometimes', 'array'],
            'delivery_info.fee' => ['sometimes', 'numeric', 'min:0'],
            'delivery_info.free_delivery_threshold' => ['sometimes', 'numeric', 'min:0'],

            'settings' => ['nullable', 'array'],
            'settings.currency' => ['sometimes', 'string', 'size:3'], // KES, USD, etc.
            'settings.payment_methods' => ['sometimes', 'array'],

            'social_media' => ['nullable', 'array'],
            'social_media.facebook' => ['sometimes', 'url', 'max:255'],
            'social_media.instagram' => ['sometimes', 'string', 'max:255'],
            'social_media.twitter' => ['sometimes', 'string', 'max:255'],
            'social_media.whatsapp' => ['sometimes', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required.',
            'business_type_id.required' => 'Please select a business type.',
            'business_type_id.exists' => 'Selected business type is invalid.',
            'business_category_id.required' => 'Please select a business category.',
            'business_category_id.exists' => 'Selected business category is invalid.',
            'business_phone.required' => 'Business phone number is required.',
            'business_logo.image' => 'Business logo must be an image.',
            'business_logo.max' => 'Business logo must not exceed 2MB.',
            'business_banner.max' => 'Business banner must not exceed 5MB.',
            'operating_hours.*.open.date_format' => 'Time must be in HH:MM format (e.g., 09:00).',
            'settings.currency.size' => 'Currency code must be 3 characters (e.g., KES).',
        ];
    }
}
