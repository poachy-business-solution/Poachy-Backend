<?php

namespace App\Http\Requests\Central\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

class InitiateUpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
        ];
    }
}
