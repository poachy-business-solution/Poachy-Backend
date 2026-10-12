<?php

namespace App\Http\Requests\Public\Signup;

use Illuminate\Foundation\Http\FormRequest;

class VerifySignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'signup_id' => ['required', 'uuid'],
            'phone_code' => ['required', 'digits:6'],
            'email_code' => ['required', 'digits:6'],
        ];
    }
}
