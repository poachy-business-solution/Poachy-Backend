<?php

namespace App\Http\Requests\Internal\Admin;

class ExtendTrialRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'days' => ['required', 'integer', 'min:1', 'max:90'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
