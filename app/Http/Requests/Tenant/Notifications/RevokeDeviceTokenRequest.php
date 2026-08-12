<?php

namespace App\Http\Requests\Tenant\Notifications;

use Illuminate\Foundation\Http\FormRequest;

class RevokeDeviceTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
        ];
    }
}
