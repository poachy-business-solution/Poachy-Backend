<?php

namespace App\Http\Requests\Central\Marketplace\Returns;

use Illuminate\Foundation\Http\FormRequest;

class DisputeReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the case must be the buyer's own (checked in the controller)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.config('returns.max_photos', 4)],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }
}
