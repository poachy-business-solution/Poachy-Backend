<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use Illuminate\Foundation\Http\FormRequest;

class ApproveWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has permission to approve waste
        return $this->user()->can('manage-waste-records');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
