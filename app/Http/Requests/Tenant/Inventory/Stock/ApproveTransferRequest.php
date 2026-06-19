<?php

namespace App\Http\Requests\Tenant\Inventory\Stock;

use Illuminate\Foundation\Http\FormRequest;

class ApproveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('transfer-stock');
    }

    public function rules(): array
    {
        return [];
    }
}
