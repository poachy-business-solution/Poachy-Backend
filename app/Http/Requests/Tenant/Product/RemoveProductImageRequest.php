<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class RemoveProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    /** The app sends the URLs it was given; the service matches stored paths. */
    protected function prepareForValidation(): void
    {
        $images = $this->input('images');

        if (is_array($images)) {
            $this->merge(['images' => array_map(fn ($i) => is_string($i) ? self::toStoragePath($i) : $i, $images)]);
        }
    }

    public static function toStoragePath(string $image): string
    {
        if (filter_var($image, FILTER_VALIDATE_URL)) {
            $image = (string) parse_url($image, PHP_URL_PATH);
            foreach (['#^/tenancy/assets/#', '#^/api/v1/public/assets/[^/]+/#', '#^/storage/#'] as $prefix) {
                $image = preg_replace($prefix, '', $image, 1);
            }
        }

        return ltrim($image, '/');
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
