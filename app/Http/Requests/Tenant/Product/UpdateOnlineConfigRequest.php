<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOnlineConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-inventory') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_available_online' => 'sometimes|required|boolean',
            'online_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'online_description' => 'nullable|string|max:5000',
            'notes' => 'nullable|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'is_available_online.required' => 'Please specify if product should be available online',
            'online_price.min' => 'Online price cannot be negative',
            'online_description.max' => 'Online description cannot exceed 5000 characters',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->is_available_online && ! $this->has('online_price')) {
            $product = $this->route('product');

            if ($product) {
                $this->merge(['online_price' => $product->base_selling_price]);
            }
        }
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $isAvailable = $this->is_available_online;
            $onlinePrice = $this->online_price;

            // If making available online, online_price is required
            if ($isAvailable && empty($onlinePrice)) {
                $validator->errors()->add(
                    'online_price',
                    'Online price is required when making product available online'
                );
            }
        });
    }
}
