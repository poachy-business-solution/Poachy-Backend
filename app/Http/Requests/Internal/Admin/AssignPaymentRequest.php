<?php

namespace App\Http\Requests\Internal\Admin;

class AssignPaymentRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'shop' => ['required', 'string', 'max:64'],   // tenant id or POA account
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
