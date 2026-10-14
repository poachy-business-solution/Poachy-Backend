<?php

namespace App\Http\Requests\Tenant\Business;

use App\Helpers\PhoneNumber;
use App\Models\BusinessDetail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Owner edits to the shop's public page: slug, GPS pin, WhatsApp number, return window. */
class UpdateShopPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:owner middleware on the route
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $ownId = BusinessDetail::on('central')->where('tenant_id', tenant()->id)->value('id');

        return [
            'slug' => ['sometimes', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])$/',
                Rule::unique('central.business_details', 'slug')->ignore($ownId)],
            'whatsapp_number' => ['sometimes', 'nullable', 'string', 'max:20', function ($attr, $value, $fail) {
                if ($value !== null && $value !== '' && (! is_string($value) || ! PhoneNumber::isValid($value))) {
                    $fail('Enter a Kenyan mobile number for WhatsApp.');
                }
            }],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'return_window_days' => ['sometimes', 'integer', Rule::in(BusinessDetail::RETURN_WINDOWS)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Use lowercase letters, numbers and hyphens only.',
            'slug.unique' => 'Another shop already uses that link.',
            'return_window_days.in' => 'Returns can be off, 3 days or 7 days.',
            'latitude.required_with' => 'A GPS pin needs both latitude and longitude.',
            'longitude.required_with' => 'A GPS pin needs both latitude and longitude.',
        ];
    }
}
