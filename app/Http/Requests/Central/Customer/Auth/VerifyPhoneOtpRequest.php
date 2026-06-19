<?php

namespace App\Http\Requests\Central\Customer\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    public function rules(): array
    {
        return [
            'otp_code' => ['required', 'string', 'digits:7'],
        ];
    }
}
