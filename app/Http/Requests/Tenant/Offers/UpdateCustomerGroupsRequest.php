<?php

namespace App\Http\Requests\Tenant\Offers;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerGroupsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-promotions');
    }

    public function rules(): array
    {
        return [
            'customer_group_ids' => ['nullable', 'array'],
            'customer_group_ids.*' => ['required', 'integer', 'exists:customer_groups,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_group_ids.array' => 'Customer group IDs must be an array.',
            'customer_group_ids.*.required' => 'Customer group ID is required.',
            'customer_group_ids.*.exists' => 'Selected customer group does not exist.',
        ];
    }
}
