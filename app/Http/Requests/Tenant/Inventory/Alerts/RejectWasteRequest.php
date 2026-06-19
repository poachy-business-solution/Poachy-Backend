<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use Illuminate\Foundation\Http\FormRequest;

class RejectWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Check if user has permission to reject waste
        return $this->user()->can('manage-waste-records');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Please provide a reason for rejecting this waste record.',
        ];
    }
}
