<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        return [
            'banner_image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:2048', // 2MB max
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'banner_image.required' => 'Banner image is required.',
            'banner_image.file' => 'Banner must be a valid file.',
            'banner_image.image' => 'Banner must be an image file.',
            'banner_image.mimes' => 'Banner must be a JPEG, JPG, PNG, or WEBP image.',
            'banner_image.max' => 'Banner image must not exceed 2MB.',
        ];
    }
}
