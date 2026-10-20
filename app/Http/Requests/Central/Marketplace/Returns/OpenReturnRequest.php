<?php

namespace App\Http\Requests\Central\Marketplace\Returns;

use App\Models\MarketplaceReturnCase;
use Illuminate\Foundation\Http\FormRequest;

class OpenReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // the order must be the buyer's own (checked in the controller)
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.order_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'reason_code' => ['required', 'string', 'in:'.implode(',', MarketplaceReturnCase::REASONS)],
            'description' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.config('returns.max_photos', 4)],
            'photos.*' => ['image', 'mimes:jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_code.in' => 'Choose a reason: changed mind, faulty, wrong item, not as described or damaged.',
            'photos.*.max' => 'Each photo can be up to 5 MB.',
        ];
    }
}
