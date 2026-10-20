<?php

namespace App\Http\Requests\Internal\Admin;

use Illuminate\Foundation\Http\FormRequest;

class DecideReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // internal.admin middleware checks the role
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:refund_full,refund_partial,no_refund'],
            'refund_amount' => ['required_if:decision,refund_partial', 'nullable', 'numeric', 'min:0.01'],
            'note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }
}
