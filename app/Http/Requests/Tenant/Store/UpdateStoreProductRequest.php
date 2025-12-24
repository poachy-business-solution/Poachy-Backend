<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreProductRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'store_selling_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
            'min_stock_level' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'is_available' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_selling_price.numeric' => 'Store selling price must be a valid number.',
            'store_selling_price.min' => 'Store selling price cannot be negative.',
            'min_stock_level.integer' => 'Minimum stock level must be a whole number.',
            'min_stock_level.min' => 'Minimum stock level cannot be negative.',
        ];
    }
}
