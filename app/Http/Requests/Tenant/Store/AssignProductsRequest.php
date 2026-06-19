<?php

namespace App\Http\Requests\Tenant\Store;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled by policy/middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'product_ids' => [
                'required',
                'array',
                'min:1',
            ],
            'product_ids.*' => [
                'required',
                'integer',
                Rule::exists('tenant.products', 'id')
                    ->where('is_active', true),
            ],
            'auto_assign_variants' => [
                'sometimes',
                'boolean',
            ],
            'auto_assign_bundles' => [
                'sometimes',
                'boolean',
            ],
            'store_selling_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999999.99',
            ],
            'min_stock_level' => [
                'sometimes',
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
            'product_ids.required' => 'At least one product must be selected.',
            'product_ids.*.exists' => 'One or more selected products do not exist or are inactive.',
            'store_selling_price.numeric' => 'Store selling price must be a valid number.',
            'store_selling_price.min' => 'Store selling price cannot be negative.',
            'min_stock_level.integer' => 'Minimum stock level must be a whole number.',
            'min_stock_level.min' => 'Minimum stock level cannot be negative.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set defaults
        $this->merge([
            'auto_assign_variants' => $this->input('auto_assign_variants', true),
            'auto_assign_bundles' => $this->input('auto_assign_bundles', false),
            'min_stock_level' => $this->input('min_stock_level', 0),
            'is_available' => $this->input('is_available', true),
        ]);
    }

    /**
     * Get validated data with defaults applied.
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        // Ensure defaults are in validated data
        $validated['auto_assign_variants'] = $validated['auto_assign_variants'] ?? true;
        $validated['auto_assign_bundles'] = $validated['auto_assign_bundles'] ?? false;
        $validated['min_stock_level'] = $validated['min_stock_level'] ?? 0;
        $validated['is_available'] = $validated['is_available'] ?? true;

        return $key ? data_get($validated, $key, $default) : $validated;
    }
}
