<?php

namespace App\Http\Requests\Tenant\Business;

use App\Enums\Central\DeliveryMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'zone_name'  => ['required', 'string', 'max:100'],
            'zone_type'  => ['required', 'string', 'in:city,county,postal_code,radius'],

            // City-based
            'cities'   => ['required_if:zone_type,city', 'nullable', 'array', 'min:1'],
            'cities.*' => ['string', 'max:100'],

            // County-based
            'counties'   => ['required_if:zone_type,county', 'nullable', 'array', 'min:1'],
            'counties.*' => ['string', 'max:100'],

            // Postal-code-based
            'postal_codes'   => ['required_if:zone_type,postal_code', 'nullable', 'array', 'min:1'],
            'postal_codes.*' => ['string', 'max:20'],

            // Radius-based
            'latitude'  => ['required_if:zone_type,radius', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:zone_type,radius', 'nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['required_if:zone_type,radius', 'nullable', 'integer', 'min:1', 'max:500'],

            // Fees
            'standard_fee'  => ['required', 'numeric', 'min:0'],
            'express_fee'   => ['nullable', 'numeric', 'min:0'],
            'scheduled_fee' => ['nullable', 'numeric', 'min:0'],

            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],

            // Estimated delivery times
            'standard_delivery_time'  => ['nullable', 'string', 'max:100'],
            'express_delivery_time'   => ['nullable', 'string', 'max:100'],
            'scheduled_delivery_time' => ['nullable', 'string', 'max:100'],

            // Supported methods (must include at least 'standard')
            'supported_methods'   => ['required', 'array', 'min:1'],
            'supported_methods.*' => ['string', 'in:' . implode(',', DeliveryMethod::values())],

            'priority'  => ['nullable', 'integer', 'min:1', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $supportedMethods = $this->supported_methods ?? [];

            if (in_array(DeliveryMethod::Express->value, $supportedMethods) && $this->express_fee === null) {
                $validator->errors()->add(
                    'express_fee',
                    'Express delivery fee is required when express delivery is a supported method.'
                );
            }

            if (in_array(DeliveryMethod::Scheduled->value, $supportedMethods) && $this->scheduled_fee === null) {
                $validator->errors()->add(
                    'scheduled_fee',
                    'Scheduled delivery fee is required when scheduled delivery is a supported method.'
                );
            }

            if (
                $this->free_delivery_threshold !== null &&
                (float) $this->free_delivery_threshold > 0 &&
                (float) $this->free_delivery_threshold < (float) ($this->standard_fee ?? 0)
            ) {
                $validator->errors()->add(
                    'free_delivery_threshold',
                    'Free delivery threshold should be greater than or equal to the standard delivery fee.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'cities.required_if'       => 'At least one city is required for city-based zones.',
            'counties.required_if'     => 'At least one county is required for county-based zones.',
            'postal_codes.required_if' => 'At least one postal code is required for postal code zones.',
            'latitude.required_if'     => 'Latitude is required for radius-based zones.',
            'longitude.required_if'    => 'Longitude is required for radius-based zones.',
            'radius_km.required_if'    => 'Radius (km) is required for radius-based zones.',
            'supported_methods.*.in'   => 'Invalid delivery method. Must be one of: ' . implode(', ', DeliveryMethod::values()),
        ];
    }
}
