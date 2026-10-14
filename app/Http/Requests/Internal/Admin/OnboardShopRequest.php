<?php

namespace App\Http\Requests\Internal\Admin;

use App\Helpers\PhoneNumber;

class OnboardShopRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20', function ($attr, $value, $fail) {
                if (! is_string($value) || ! PhoneNumber::isValid($value)) {
                    $fail('Enter a Kenyan mobile number.');
                }
            }],
            'email' => ['required', 'email:rfc', 'max:191'],
            'town' => ['required', 'string', 'min:2', 'max:120'],
            'workspace' => ['required', 'string', 'max:40'],
            'branches' => ['nullable', 'integer', 'min:1', 'max:50'],
            'business_category_id' => ['required', 'integer', 'exists:central.business_categories,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'business_category_id.required' => 'Choose the type of shop.',
            'business_category_id.exists' => 'Choose the type of shop from the list.',
        ];
    }
}
