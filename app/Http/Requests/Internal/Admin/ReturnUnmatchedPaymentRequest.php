<?php

namespace App\Http\Requests\Internal\Admin;

class ReturnUnmatchedPaymentRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],   // e.g. the reversal's M-Pesa receipt
        ];
    }
}
