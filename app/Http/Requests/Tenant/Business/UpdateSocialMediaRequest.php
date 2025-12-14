<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSocialMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'social_media' => ['nullable', 'array'],
            'social_media.facebook' => ['nullable', 'url', 'max:255'],
            'social_media.instagram' => ['nullable', 'string', 'max:100'],
            'social_media.twitter' => ['nullable', 'string', 'max:100'],
            'social_media.whatsapp' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'social_media.required' => 'Social media information is required.',
            'social_media.facebook.url' => 'Facebook must be a valid URL.',
            'social_media.facebook.max' => 'Facebook URL cannot exceed 255 characters.',
            'social_media.instagram.max' => 'Instagram handle cannot exceed 100 characters.',
            'social_media.twitter.max' => 'Twitter handle cannot exceed 100 characters.',
            'social_media.whatsapp.max' => 'WhatsApp number cannot exceed 20 characters.',
        ];
    }

    /**
     * Custom validation and sanitization.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $socialMedia = $this->social_media;

            // Validate Instagram handle format (optional)
            if (isset($socialMedia['instagram']) && !empty($socialMedia['instagram'])) {
                $instagram = $socialMedia['instagram'];
                // Remove @ if present at start
                if (str_starts_with($instagram, '@')) {
                    $instagram = substr($instagram, 1);
                }

                // Validate format (alphanumeric, underscores, dots)
                if (!preg_match('/^[a-zA-Z0-9._]+$/', $instagram)) {
                    $validator->errors()->add(
                        'social_media.instagram',
                        'Instagram handle can only contain letters, numbers, dots, and underscores.'
                    );
                }
            }

            // Validate Twitter handle format (optional)
            if (isset($socialMedia['twitter']) && !empty($socialMedia['twitter'])) {
                $twitter = $socialMedia['twitter'];
                // Remove @ if present at start
                if (str_starts_with($twitter, '@')) {
                    $twitter = substr($twitter, 1);
                }

                // Validate format (alphanumeric, underscores)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $twitter)) {
                    $validator->errors()->add(
                        'social_media.twitter',
                        'Twitter handle can only contain letters, numbers, and underscores.'
                    );
                }
            }

            // Validate WhatsApp number format
            if (isset($socialMedia['whatsapp']) && !empty($socialMedia['whatsapp'])) {
                $whatsapp = $socialMedia['whatsapp'];
                // Should start with + and contain only digits and spaces
                if (!preg_match('/^\+?[0-9\s]+$/', $whatsapp)) {
                    $validator->errors()->add(
                        'social_media.whatsapp',
                        'WhatsApp number must be a valid phone number (e.g., +254712345678).'
                    );
                }
            }
        });
    }
}
