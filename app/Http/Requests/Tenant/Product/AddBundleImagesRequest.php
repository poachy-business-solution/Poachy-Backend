<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class AddBundleImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048', // 2MB max
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'At least one image file is required',
            'images.max' => 'Maximum 5 images allowed per upload',
            'images.*.image' => 'Each file must be an image',
            'images.*.mimes' => 'Images must be of type: jpeg, jpg, png, or webp',
            'images.*.max' => 'Each image must not exceed 2MB',
        ];
    }
}
