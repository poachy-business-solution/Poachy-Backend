<?php

namespace App\Http\Requests\Tenant\Marketplace;

use Illuminate\Foundation\Http\FormRequest;

/** The cashier links the sale they just recorded to a buyer's PCH- code. */
class RedeemReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'sale_id' => ['required', 'integer', 'exists:tenant.sales,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sale_id.required' => 'Record the sale first, then redeem the code with its sale id.',
            'sale_id.exists' => 'That sale was not found in this shop.',
        ];
    }
}
