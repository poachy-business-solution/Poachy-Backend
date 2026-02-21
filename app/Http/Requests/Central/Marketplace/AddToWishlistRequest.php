<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Helpers\CustomerHelper;
use App\Models\Wishlist;
use Illuminate\Foundation\Http\FormRequest;

class AddToWishlistRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'marketplace_product_id' => ['required', 'integer', 'exists:central.marketplace_products,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'desired_quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'marketplace_product_id.exists' => 'The selected product does not exist.',
            'desired_quantity.min' => 'Desired quantity must be at least 1.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            try {
                $customer = CustomerHelper::getAuthenticatedCustomerOrFail();

                // Check if adding this item would exceed the limit (only for new items)
                $existingItem = Wishlist::on('central')
                    ->where('customer_id', $customer->id)
                    ->where('marketplace_product_id', $this->marketplace_product_id)
                    ->first();

                if (! $existingItem) {
                    $currentCount = Wishlist::on('central')
                        ->where('customer_id', $customer->id)
                        ->count();

                    $maxItems = 100;

                    if ($currentCount >= $maxItems) {
                        $validator->errors()->add(
                            'wishlist',
                            "Wishlist cannot exceed {$maxItems} items."
                        );
                    }
                }
            } catch (\Exception $e) {
                // Let the controller handle authentication issues
            }
        });
    }
}
