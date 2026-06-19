<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoresRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        return [
            'store_ids' => ['nullable', 'array'],
            'store_ids.*' => ['required', 'integer', 'exists:stores,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_ids.array' => 'Store IDs must be an array.',
            'store_ids.*.required' => 'Store ID is required.',
            'store_ids.*.exists' => 'Selected store does not exist.',
        ];
    }
}
