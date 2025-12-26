<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class AttachBrandsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-coupons');
    }

    public function rules(): array
    {
        return [
            'brand_ids' => ['required', 'array', 'min:1'],
            'brand_ids.*' => ['required', 'integer', 'exists:product_brands,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'brand_ids.required' => 'At least one brand is required.',
            'brand_ids.min' => 'At least one brand is required.',
            'brand_ids.*.required' => 'Brand ID is required.',
            'brand_ids.*.exists' => 'Selected brand does not exist.',
        ];
    }
}
