<?php

namespace App\Http\Requests\Central\Customer\Address;

use App\Enums\Central\AddressType;
use App\Helpers\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'address_type'          => ['nullable', 'string', 'in:' . implode(',', AddressType::values())],
            'label'                 => ['nullable', 'string', 'max:50'],
            'recipient_name'        => ['required', 'string', 'max:100'],
            'recipient_phone'       => ['required', 'string', 'max:20'],
            'address_line'          => ['required', 'string', 'max:255'],
            'building_apartment'    => ['nullable', 'string', 'max:100'],
            'city'                  => ['required', 'string', 'max:100'],
            'county'                => ['required', 'string', 'max:100'],
            'postal_code'           => ['nullable', 'string', 'max:20'],
            'latitude'              => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'             => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
            'is_default'            => ['nullable', 'boolean'],
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
