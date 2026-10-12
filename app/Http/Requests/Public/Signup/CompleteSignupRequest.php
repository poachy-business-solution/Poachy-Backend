<?php

namespace App\Http\Requests\Public\Signup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CompleteSignupRequest extends FormRequest
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
            'workspace' => ['required', 'string', 'max:40'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
            'accept_terms' => ['accepted'],
        ];
    }
}
