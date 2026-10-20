<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdvertTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // internal.admin middleware checks the role
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'min:10', 'max:1000000'],
            'days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
