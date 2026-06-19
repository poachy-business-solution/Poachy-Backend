<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class AddProductImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'file|image|mimes:jpeg,jpg,png|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'At least one image is required',
            'images.min' => 'At least one image is required',
            'images.max' => 'You can add a maximum of 5 images at once',
            'images.*.file' => 'Each image must be a file',
            'images.*.image' => 'Each image must be an image',
            'images.*.mimes' => 'Each image must be a JPEG, JPG, or PNG',
            'images.*.max' => 'Each image must be less than 2MB',
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');
            $existingCount = count($product->secondary_images ?? []);
            $newCount = count($this->images ?? []);
            $totalCount = $existingCount + $newCount;

            if ($totalCount > 5) {
                $validator->errors()->add(
                    'images',
                    "Cannot add {$newCount} images. Product already has {$existingCount} images. Maximum is 5 total."
                );
            }
        });
    }
}
