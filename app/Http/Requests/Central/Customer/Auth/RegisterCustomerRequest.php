<?php

namespace App\Http\Requests\Central\Customer\Auth;

use App\Enums\Central\Gender;
use App\Helpers\PhoneNumberNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:100'],
            'email'              => ['required', 'email', 'max:150', 'unique:central.users,email'],
            'password'           => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'phone'              => ['required', 'string', 'max:20'],
            'date_of_birth'      => ['nullable', 'date', 'before:today'],
            'gender'             => ['nullable', 'string', 'in:' . implode(',', Gender::values())],
            'accepts_marketing'  => ['nullable', 'boolean'],
            'accepts_sms'        => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normalise phone so uniqueness check runs on the stored format
        if ($this->has('phone')) {
            $normalised = PhoneNumberNormalizer::normalize($this->phone);

            $this->merge(['phone' => $normalised]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $phone = $this->input('phone');

            if ($phone && !\App\Models\MarketplaceCustomer::on('central')
                    ->where('phone', $phone)->doesntExist()) {
                $validator->errors()->add('phone', 'This phone number is already registered.');
            }
        });
    }
}