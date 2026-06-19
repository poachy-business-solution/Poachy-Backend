<?php

namespace App\Http\Requests\Central\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyLoginOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email'],
            'otp_code'    => ['required', 'string', 'digits:7'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
