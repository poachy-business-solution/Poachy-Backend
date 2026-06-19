<?php

namespace App\Http\Requests\Central\Customer\Auth;

use App\Enums\Central\Gender;
use App\Helpers\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        $userId     = auth('central')->id();
        $customerId = auth('central')->user()->marketplaceCustomer?->id;

        return [
            'name'              => ['sometimes', 'string', 'max:100'],
            'email'             => ['sometimes', 'email', 'max:150',
                                    "unique:central.users,email,{$userId}"],
            'phone'             => ['sometimes', 'string', 'max:20'],
            'date_of_birth'     => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'            => ['sometimes', 'nullable', 'string',
                                    'in:' . implode(',', Gender::values())],
            'accepts_marketing' => ['sometimes', 'boolean'],
            'accepts_sms'       => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge([
                'phone' => PhoneNumberNormalizer::normalize($this->phone),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (!$this->has('phone')) {
                return;
            }

            $customerId = auth('central')->user()->marketplaceCustomer?->id;
            $phone      = $this->input('phone');

            $taken = \App\Models\MarketplaceCustomer::on('central')
                ->where('phone', $phone)
                ->where('id', '!=', $customerId)
                ->exists();

            if ($taken) {
                $validator->errors()->add('phone', 'This phone number is already in use.');
            }
        });
    }
}
