<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ModerateReviewAdminRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:approve,reject,dismiss_flags'],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
        ];
    }
}
