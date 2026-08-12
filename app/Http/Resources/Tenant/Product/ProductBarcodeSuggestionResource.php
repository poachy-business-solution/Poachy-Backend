<?php

namespace App\Http\Resources\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBarcodeSuggestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'barcode_type' => $this->barcode_type,
            'status' => $this->status,
            'is_primary' => $this->is_primary,
            'supplier_id' => $this->supplier_id,
            'region' => $this->region,
            'store_id' => $this->store_id,
            'metadata' => $this->metadata ?? [],
            'notes' => $this->notes,
            'submitted_by' => $this->submitted_by,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'rejection_reason' => $this->rejection_reason,
            'approved_barcode_id' => $this->approved_barcode_id,
            'suggested_barcodeable_type' => $this->suggested_barcodeable_type,
            'suggested_barcodeable_id' => $this->suggested_barcodeable_id,
            'suggested_barcodeable' => $this->whenLoaded('suggestedBarcodeable', fn () => $this->suggestedBarcodeablePayload()),
            'approved_barcode' => $this->whenLoaded('approvedBarcode', fn () => $this->approvedBarcode ? new ProductBarcodeResource($this->approvedBarcode) : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function suggestedBarcodeablePayload(): ?array
    {
        $entity = $this->suggestedBarcodeable;

        if ($entity instanceof Product) {
            return [
                'type' => 'product',
                'id' => $entity->id,
                'uuid' => $entity->uuid,
                'name' => $entity->name,
                'sku' => $entity->sku,
                'is_active' => $entity->is_active,
            ];
        }

        if ($entity instanceof ProductVariant) {
            return [
                'type' => 'variant',
                'id' => $entity->id,
                'uuid' => $entity->uuid,
                'product_id' => $entity->product_id,
                'variant_name' => $entity->variant_name,
                'sku' => $entity->sku,
                'is_active' => $entity->is_active,
            ];
        }

        if ($entity instanceof ProductUom) {
            return [
                'type' => 'product_uom',
                'id' => $entity->id,
                'product_id' => $entity->product_id,
                'uom_id' => $entity->uom_id,
                'is_sales_uom' => $entity->is_sales_uom,
                'conversion_to_base' => $entity->conversion_to_base,
            ];
        }

        return null;
    }
}
