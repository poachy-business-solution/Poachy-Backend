<?php

namespace App\Http\Requests\Tenant\Business;

use App\Enums\Central\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_name'  => ['sometimes', 'string', 'max:100'],
            'zone_type'  => ['sometimes', 'string', 'in:city,county,postal_code,radius'],

            // City-based
            'cities'   => ['sometimes', 'nullable', 'array', 'min:1'],
            'cities.*' => ['string', 'max:100'],

            // County-based
            'counties'   => ['sometimes', 'nullable', 'array', 'min:1'],
            'counties.*' => ['string', 'max:100'],

            // Postal-code-based
            'postal_codes'   => ['sometimes', 'nullable', 'array', 'min:1'],
            'postal_codes.*' => ['string', 'max:20'],

            // Radius-based
            'latitude'  => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],

            // Fees
            'standard_fee'  => ['sometimes', 'numeric', 'min:0'],
            'express_fee'   => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'scheduled_fee' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            'free_delivery_threshold' => ['sometimes', 'nullable', 'numeric', 'min:0'],

            // Estimated delivery times
            'standard_delivery_time'  => ['sometimes', 'nullable', 'string', 'max:100'],
            'express_delivery_time'   => ['sometimes', 'nullable', 'string', 'max:100'],
            'scheduled_delivery_time' => ['sometimes', 'nullable', 'string', 'max:100'],

            // Supported methods
            'supported_methods'   => ['sometimes', 'array', 'min:1'],
            'supported_methods.*' => ['string', 'in:' . implode(',', DeliveryMethod::values())],

            'priority'  => ['sometimes', 'integer', 'min:1', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $supportedMethods = $this->supported_methods ?? [];

            if (
                in_array(DeliveryMethod::Express->value, $supportedMethods) &&
                ! $this->has('express_fee') &&
                $this->express_fee === null
            ) {
                $validator->errors()->add(
                    'express_fee',
                    'Express delivery fee is required when express delivery is a supported method.'
                );
            }

            if (
                in_array(DeliveryMethod::Scheduled->value, $supportedMethods) &&
                ! $this->has('scheduled_fee') &&
                $this->scheduled_fee === null
            ) {
                $validator->errors()->add(
                    'scheduled_fee',
                    'Scheduled delivery fee is required when scheduled delivery is a supported method.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'supported_methods.*.in' => 'Invalid delivery method. Must be one of: ' . implode(', ', DeliveryMethod::values()),
        ];
    }
}
