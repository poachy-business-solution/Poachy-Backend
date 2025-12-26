<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class AttachCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-coupons');
    }

    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['required', 'integer', 'exists:product_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_ids.required' => 'At least one category is required.',
            'category_ids.min' => 'At least one category is required.',
            'category_ids.*.required' => 'Category ID is required.',
            'category_ids.*.exists' => 'Selected category does not exist.',
        ];
    }
}
