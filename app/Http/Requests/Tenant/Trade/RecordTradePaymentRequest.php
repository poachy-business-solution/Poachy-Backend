<?php

namespace App\Http\Requests\Tenant\Trade;

use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordTradePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id', new AssignedStore],
            'direction' => ['required', Rule::in(['to_me', 'by_me'])],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999.99'],
            'method' => ['required', Rule::in(['cash', 'mpesa'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direction.in' => 'Say who paid: to_me or by_me.',
            'amount.required' => 'Enter the amount paid.',
            'method.in' => 'Paid by cash or M-Pesa.',
        ];
    }
}
