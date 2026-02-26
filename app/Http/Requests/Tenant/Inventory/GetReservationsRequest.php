<?php

namespace App\Http\Requests\Tenant\Inventory;

use App\Enums\Tenant\ReservationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetReservationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'status' => ['nullable', 'string', Rule::enum(ReservationStatus::class)],
            'from_date' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'store_id.exists' => 'The selected store does not exist',
            'product_id.exists' => 'The selected product does not exist',
            'status.enum' => 'Invalid reservation status. Must be one of: active, fulfilled, cancelled, expired',
            'to_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
