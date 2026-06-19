<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_info'                => ['nullable', 'array'],
            'delivery_info.available'      => ['nullable', 'boolean'],
            'delivery_info.zones_enabled'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'delivery_info.array'                   => 'Delivery information must be an array.',
            'delivery_info.available.boolean'        => 'Delivery availability must be true or false.',
            'delivery_info.zones_enabled.boolean'    => 'zones_enabled must be true or false.',
        ];
    }
}
