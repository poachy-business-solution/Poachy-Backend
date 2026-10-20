<?php

namespace App\Http\Requests\Tenant\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class BuyAdvertRequest extends FormRequest
{
    /** Money: the owner only (same as balance top-ups). */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('owner') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'exists:central.advert_types,key'],
            'product_id' => ['nullable', 'integer'],
            'pay_with' => ['required', 'string', 'in:balance,mpesa'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
