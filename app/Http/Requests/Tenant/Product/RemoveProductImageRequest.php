<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class RemoveProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1',
            'images.*' => 'required|string', // Full path like "products/images/secondary_xyz.jpg"
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'Please provide at least one image to delete',
            'images.array' => 'Images must be provided as an array',
            'images.*.required' => 'Each image path is required',
            'images.*.string' => 'Each image path must be a string',
        ];
    }
}
