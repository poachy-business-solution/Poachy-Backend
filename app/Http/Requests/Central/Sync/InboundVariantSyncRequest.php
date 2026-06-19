<?php

namespace App\Http\Requests\Central\Sync;

use Illuminate\Foundation\Http\FormRequest;

class InboundVariantSyncRequest extends FormRequest
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
            'payload.variant_id' => 'required|integer',
            'payload.product_id' => 'required|integer',
            'payload.product_uuid' => 'required|string',
            'payload.product_type' => 'required|string|in:variant',
            'payload.variant_name' => 'required|string|max:255',
            'payload.sku' => 'required|string|max:255',
            'payload.attributes' => 'nullable|array',
            'payload.online_price' => 'required|numeric|min:0',
            'payload.computed_online_price' => 'numeric|min:0',
            'payload.variant_price' => 'nullable|numeric',
            'payload.tax_rate' => 'required|numeric|min:0|max:100',

            'payload.is_active' => 'boolean',
            'payload.is_featured' => 'boolean',

            // Base UOM validation
            'payload.base_uom' => 'required|array',
            'payload.base_uom.code' => 'required|string|max:20',
            'payload.base_uom.name' => 'required|string|max:255',

            // Variant UOM validation
            'payload.variant_uom' => 'required|array',
            'payload.variant_uom.code' => 'required|string|max:20',
            'payload.variant_uom.name' => 'required|string|max:255',

            // UOM quantities
            'payload.uom_quantity' => 'numeric',
            'payload.quantity_in_base_uom' => 'numeric',

            // Category validation
            'payload.category' => 'required|array',
            'payload.category.id' => 'required|integer',
            'payload.category.name' => 'required|string',
            'payload.category.slug' => 'required|string',

            // Brand validation (optional)
            'payload.brand' => 'nullable|array',
            'payload.brand.id' => 'required_with:payload.brand|integer',
            'payload.brand.name' => 'required_with:payload.brand|string',
            'payload.brand.slug' => 'required_with:payload.brand|string',

            // Parent product info
            'payload.parent_product_name' => 'string',
            'payload.parent_product_sku' => 'string',

            // Inventory validation
            'payload.inventory' => 'required|array',
            'payload.inventory.available_quantity' => 'required|numeric|min:0',
            'payload.inventory.stock_status' => 'required|string|in:in_stock,low_stock,out_of_stock',

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
            'payload.variant_id.required' => 'Variant ID is required in payload.',
            'payload.product_id.required' => 'Product ID is required in payload.',
            'payload.product_type.in' => 'Product type must be variant for variant sync.',
            'payload.online_price.min' => 'Online price must be greater than or equal to 0.',
            'payload.tax_rate.min' => 'Tax rate must be between 0 and 100.',
            'payload.tax_rate.max' => 'Tax rate must be between 0 and 100.',
        ];
    }
}
