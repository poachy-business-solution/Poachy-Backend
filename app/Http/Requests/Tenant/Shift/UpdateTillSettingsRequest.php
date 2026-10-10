<?php

namespace App\Http\Requests\Tenant\Shift;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTillSettingsRequest extends FormRequest
{
    /**
     * Only owners and managers change how tills close.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['owner', 'manager', 'admin']) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'require_cash_count' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'require_cash_count.required' => 'Say whether staff must count cash at closing.',
            'require_cash_count.boolean' => 'Use true or false.',
        ];
    }
}
