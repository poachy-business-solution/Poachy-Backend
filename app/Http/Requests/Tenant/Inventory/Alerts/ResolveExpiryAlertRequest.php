<?php

namespace App\Http\Requests\Tenant\Inventory\Alerts;

use App\Enums\Tenant\ResolutionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ResolveExpiryAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resolve-expiry-alerts');
    }

    public function rules(): array
    {
        return [
            'resolution_action' => ['required', new Enum(ResolutionAction::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'resolution_action.required' => 'Please specify how the expiry alert was resolved.',
        ];
    }
}
