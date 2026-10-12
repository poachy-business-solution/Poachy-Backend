<?php

namespace App\Http\Requests\Internal\Admin;

/** Suspend / restore: a reason is always recorded. */
class ReasonRequest extends InternalAdminRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'min:3', 'max:1000']];
    }
}
