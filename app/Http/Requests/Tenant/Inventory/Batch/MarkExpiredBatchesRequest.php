<?php

namespace App\Http\Requests\Tenant\Inventory\Batch;

use Illuminate\Foundation\Http\FormRequest;

class MarkExpiredBatchesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-inventory');
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ];
    }
}
