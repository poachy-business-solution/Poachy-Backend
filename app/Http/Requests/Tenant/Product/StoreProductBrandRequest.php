<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductBrandRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('product_brands', 'slug'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'logo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB max
            ],
            'display_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'is_featured' => [
                'sometimes',
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
            'name.required' => 'Brand name is required',
            'name.max' => 'Brand name cannot exceed 255 characters',
            'slug.unique' => 'This slug is already in use',
            'slug.regex' => 'Slug must be lowercase letters, numbers, and hyphens only',
            // 'logo.required' => 'Brand logo is required',
            'logo.image' => 'Logo must be an image file',
            'logo.mimes' => 'Logo must be a file of type: jpeg, jpg, png, webp',
            'logo.max' => 'Logo size cannot exceed 2MB',
            'display_order.min' => 'Display order must be at least 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'is_active' => 'active status',
            'is_featured' => 'featured status',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default display_order if not provided
        if (!$this->has('display_order')) {
            $this->merge(['display_order' => 0]);
        }
    }
}
