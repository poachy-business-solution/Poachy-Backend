<?php

namespace App\Http\Requests\Internal\Admin;

class VerificationDecisionRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject,revoke'],
            'reason' => ['required_unless:decision,approve', 'nullable', 'string', 'max:1000'],
        ];
    }
}
