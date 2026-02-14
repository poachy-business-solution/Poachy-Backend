<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundBundleSyncRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Validate API token
        $expectedToken = config('services.central_api.token');
        $providedToken = $this->bearerToken();

        return $providedToken === $expectedToken;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tenant_id' => 'required|string|exists:tenants,id',
            'action' => 'required|string|in:create,update,delete,activate,deactivate',
            'priority' => 'required|integer|min:1|max:10',
            'idempotency_key' => 'required|string|max:100',

            // Payload validation
            'payload' => 'required|array',
            'payload.bundle_id' => 'required|integer',
            'payload.bundle_uuid' => 'required|string',
            'payload.product_type' => 'required|string|in:bundle',
            'payload.bundle_name' => 'required|string|max:255',
            'payload.bundle_sku' => 'required|string|max:255',
            'payload.description' => 'nullable|string',
            'payload.online_description' => 'nullable|string',
            'payload.bundle_price' => 'required|numeric|min:0',
            'payload.online_price' => 'required|numeric|min:0',
            'payload.calculated_individual_price' => 'nullable|numeric|min:0',
            'payload.discount_amount' => 'nullable|numeric|min:0',
            'payload.savings_percentage' => 'nullable|numeric|min:0|max:100',
            'payload.tax_rate' => 'required|numeric|min:0|max:100',

            'payload.is_active' => 'boolean',
            'payload.is_available_online' => 'boolean',

            // Base UOM validation
            'payload.base_uom' => 'required|array',
            'payload.base_uom.code' => 'required|string|max:20',
            'payload.base_uom.name' => 'required|string|max:255',

            // Bundle items validation
            'payload.items' => 'required|array|min:2',
            'payload.items.*.product_id' => 'required|integer',
            'payload.items.*.product_name' => 'required|string|max:255',
            'payload.items.*.product_sku' => 'required|string|max:255',
            'payload.items.*.variant_id' => 'nullable|integer',
            'payload.items.*.variant_name' => 'nullable|string|max:255',
            'payload.items.*.variant_sku' => 'nullable|string|max:255',
            'payload.items.*.quantity' => 'required|numeric|min:0.01',
            'payload.items.*.quantity_in_base_uom' => 'nullable|numeric|min:0',
            'payload.items.*.uom_code' => 'required|string|max:20',
            'payload.items.*.uom_name' => 'required|string|max:255',
            'payload.items.*.item_price' => 'nullable|numeric|min:0',
            'payload.items.*.total_price' => 'nullable|numeric|min:0',

            // Metadata
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The specified tenant does not exist.',
            'action.in' => 'Invalid sync action. Must be create, update, delete, activate, or deactivate.',
            'payload.bundle_id.required' => 'Bundle ID is required in payload.',
            'payload.product_type.in' => 'Product type must be bundle for bundle sync.',
            'payload.online_price.min' => 'Online price must be greater than or equal to 0.',
            'payload.tax_rate.min' => 'Tax rate must be between 0 and 100.',
            'payload.tax_rate.max' => 'Tax rate must be between 0 and 100.',
            'payload.items.required' => 'Bundle must contain at least 2 items.',
            'payload.items.min' => 'Bundle must contain at least 2 items.',
        ];
    }
}
