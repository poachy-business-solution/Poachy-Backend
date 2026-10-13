<?php

namespace App\Http\Requests\Public\Signup;

use App\Helpers\PhoneNumber;
use App\Services\Shared\Turnstile\TurnstileVerifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'business_category_id.required' => 'Choose the type of shop.',
            'business_category_id.exists' => 'Choose the type of shop from the list.',
        ];
    }

    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'min:2', 'max:120'],
            'owner_name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'town' => ['required', 'string', 'min:2', 'max:120'],
            'branches' => ['sometimes', 'integer', 'min:1', 'max:50'],
            // The shop type picks the starter catalogue (categories, units) — a shoe shop must not get supermarket shelves.
            'business_category_id' => ['required', 'integer', 'exists:central.business_categories,id'],
            'turnstile_token' => ['required', 'string', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $phone = (string) $this->input('phone');
            if (! PhoneNumber::isValid($phone) || ! PhoneNumber::isKenyanMobile($phone)) {
                $validator->errors()->add('phone', 'Enter a Kenyan mobile number.');
            }
            if (! app(TurnstileVerifier::class)->passes((string) $this->input('turnstile_token'), $this->ip())) {
                $validator->errors()->add('turnstile_token', 'Please confirm you are not a robot and try again.');
            }
        }];
    }
}
