<?php

namespace App\Http\Requests\Tenant\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserStoresRequest extends FormRequest
{
    /**
     * Route middleware limits this to owners and managers.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_ids' => ['present', 'array'],
            'store_ids.*' => ['integer', 'distinct', Rule::exists('stores', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'store_ids.present' => 'Send the branches this person works at (an empty list means the main branch only).',
            'store_ids.array' => 'Branches must be a list.',
            'store_ids.*.exists' => 'One of the branches does not exist or is closed.',
            'store_ids.*.distinct' => 'A branch is listed twice.',
        ];
    }
}
