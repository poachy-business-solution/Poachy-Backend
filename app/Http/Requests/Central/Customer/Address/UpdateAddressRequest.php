<?php

namespace App\Http\Requests\Central\Customer\Address;

use App\Enums\Central\AddressType;
use App\Helpers\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'address_type'          => ['sometimes', 'string', 'in:' . implode(',', AddressType::values())],
            'label'                 => ['sometimes', 'nullable', 'string', 'max:50'],
            'recipient_name'        => ['sometimes', 'string', 'max:100'],
            'recipient_phone'       => ['sometimes', 'string', 'max:20'],
            'address_line'          => ['sometimes', 'string', 'max:255'],
            'building_apartment'    => ['sometimes', 'nullable', 'string', 'max:100'],
            'city'                  => ['sometimes', 'string', 'max:100'],
            'county'                => ['sometimes', 'string', 'max:100'],
            'postal_code'           => ['sometimes', 'nullable', 'string', 'max:20'],
            'latitude'              => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'delivery_instructions' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_default'            => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('recipient_phone')) {
            $this->merge([
                'recipient_phone' => PhoneNumberNormalizer::normalize($this->recipient_phone),
            ]);
        }
    }
}
