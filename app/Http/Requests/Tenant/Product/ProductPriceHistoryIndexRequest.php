<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class ProductPriceHistoryIndexRequest extends FormRequest
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
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'changed_by' => ['nullable', 'integer', 'exists:users,id'],
            'sort_by' => ['nullable', 'string', 'in:created_at,new_selling_price,old_selling_price'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'The selected product does not exist.',
            'variant_id.exists' => 'The selected variant does not exist.',
            'from_date.date_format' => 'The from date must be in Y-m-d format.',
            'to_date.date_format' => 'The to date must be in Y-m-d format.',
            'to_date.after_or_equal' => 'The to date must be after or equal to from date.',
            'changed_by.exists' => 'The selected user does not exist.',
            'sort_by.in' => 'Invalid sort field.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
            'per_page.max' => 'Maximum 100 items per page allowed.',
        ];
    }
}
