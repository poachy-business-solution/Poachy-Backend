<?php

namespace App\Http\Requests\Tenant\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

class InspectReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware: manage-marketplace-sales
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:accept,partial,reject'],
            'refund_amount' => ['required_if:decision,partial', 'nullable', 'numeric', 'min:0.01'],
            'reason' => ['required_unless:decision,accept', 'nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.config('returns.max_photos', 4)],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required_unless' => 'Say why, so the buyer understands.',
            'refund_amount.required_if' => 'Enter the amount to refund.',
        ];
    }
}
