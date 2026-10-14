<?php

namespace App\Http\Requests\Central\Marketplace;

use App\Services\Central\Marketplace\ReservationService;
use Illuminate\Foundation\Http\FormRequest;

class CreateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('central')->check();
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'product_slug' => ['required_without:product_id', 'nullable', 'string', 'max:255'],
            'product_id' => ['required_without:product_slug', 'nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:1', 'max:'.ReservationService::MAX_QUANTITY],
            'ref' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'product_slug.required_without' => 'Say which item to reserve.',
            'quantity.max' => 'You can reserve up to '.ReservationService::MAX_QUANTITY.' of an item.',
            'ref.regex' => 'The link source may only contain letters, numbers, - and _.',
        ];
    }
}
