<?php

namespace App\Http\Requests\Tenant\Trade;

use Illuminate\Foundation\Http\FormRequest;

class StoreTradePartnerRequest extends FormRequest
{
    /**
     * Route middleware requires manage-trade-partners.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter the shop name.',
            'name.max' => 'Shop name is too long.',
        ];
    }
}
