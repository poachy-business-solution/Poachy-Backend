<?php

namespace App\Http\Requests\Tenant\Product;

use Illuminate\Foundation\Http\FormRequest;

class ReviewProductBarcodeSuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-products') ?? false;
    }

    public function rules(): array
    {
        return [
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'rejection_reason' => ['nullable', 'required_if:review_action,rejected', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_action' => str_contains($this->route()?->getActionMethod() ?? '', 'reject')
                ? 'rejected'
                : 'approved',
        ]);
    }
}
