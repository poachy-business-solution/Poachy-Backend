<?php

namespace App\Http\Requests\Tenant\Business;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => ['nullable', 'array'],
            'settings.currency' => ['nullable', 'string', 'size:3'],
            'settings.tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'settings.enable_online_store' => ['nullable', 'boolean'],
            'settings.enable_marketplace' => ['nullable', 'boolean'],
            'settings.payment_methods' => ['nullable', 'array', 'min:1'],
            'settings.payment_methods.*' => ['string', 'in:cash,mpesa,card,bank_transfer,paypal'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.array' => 'Settings must be an array.',
            'settings.currency.size' => 'Currency must be a 3-letter code (e.g., KES, USD).',
            'settings.tax_rate.numeric' => 'Tax rate must be a number.',
            'settings.tax_rate.min' => 'Tax rate cannot be negative.',
            'settings.tax_rate.max' => 'Tax rate cannot exceed 100%.',
            'settings.enable_online_store.boolean' => 'Online store must be enabled or disabled (true/false).',
            'settings.enable_marketplace.boolean' => 'Marketplace must be enabled or disabled (true/false).',
            'settings.payment_methods.array' => 'Payment methods must be an array.',
            'settings.payment_methods.min' => 'At least one payment method must be selected.',
            'settings.payment_methods.*.in' => 'Invalid payment method. Allowed: cash, mpesa, card, bank_transfer, paypal.',
        ];
    }

    /**
     * Custom validation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->settings;

            if (!$settings) {
                return;
            }

            // Ensure currency is uppercase
            if (isset($settings['currency'])) {
                $this->merge([
                    'settings' => array_merge($settings, [
                        'currency' => strtoupper($settings['currency'])
                    ])
                ]);
            }

            // Check for duplicate payment methods
            if (isset($settings['payment_methods']) && is_array($settings['payment_methods'])) {
                if (count($settings['payment_methods']) !== count(array_unique($settings['payment_methods']))) {
                    $validator->errors()->add(
                        'settings.payment_methods',
                        'Duplicate payment methods are not allowed.'
                    );
                }
            }
        });
    }
}
