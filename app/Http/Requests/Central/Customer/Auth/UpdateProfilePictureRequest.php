<?php

namespace App\Http\Requests\Central\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePictureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'profile_picture' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048', // 2 MB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'profile_picture.required' => 'Please select an image to upload.',
            'profile_picture.image'    => 'The file must be an image.',
            'profile_picture.mimes'    => 'Accepted formats: JPG, JPEG, PNG, WEBP.',
            'profile_picture.max'      => 'The image must not exceed 2 MB.',
        ];
    }
}
