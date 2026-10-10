<?php

namespace App\Http\Requests\Tenant\Trade;

use App\Rules\Tenant\AssignedStore;
use Illuminate\Foundation\Http\FormRequest;

class RecordTradeReturnRequest extends FormRequest
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
            'returns' => ['required', 'array', 'min:1', 'max:50'],
            'returns.*.trade_line_id' => ['required', 'integer', 'exists:trade_lines,id'],
            'returns.*.quantity' => ['required', 'numeric', 'gt:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'returns.required' => 'Pick what was brought back.',
            'returns.*.quantity.gt' => 'Quantity must be more than zero.',
        ];
    }
}
