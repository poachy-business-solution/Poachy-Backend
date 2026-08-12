<?php

namespace App\Http\Resources\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductUom;
use App\Models\Tenant\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBarcodeResource extends JsonResource
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
            'is_primary' => $this->is_primary,
            'is_active' => $this->is_active,
            'supplier_id' => $this->supplier_id,
            'region' => $this->region,
            'store_id' => $this->store_id,
            'valid_from' => $this->valid_from?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'source' => $this->source,
            'metadata' => $this->metadata ?? [],
            'notes' => $this->notes,
            'barcodeable_type' => $this->barcodeable_type,
            'barcodeable_id' => $this->barcodeable_id,
            'barcodeable' => $this->whenLoaded('barcodeable', fn () => $this->barcodeablePayload()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function barcodeablePayload(): ?array
    {
        $entity = $this->barcodeable;

        if ($entity instanceof Product) {
            return [
                'type' => 'product',
                'id' => $entity->id,
                'uuid' => $entity->uuid,
                'name' => $entity->name,
                'sku' => $entity->sku,
                'base_selling_price' => $entity->base_selling_price,
                'stock_status' => $entity->stock_status?->value ?? $entity->stock_status,
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
                'display_name' => $entity->display_name,
                'sku' => $entity->sku,
                'computed_price' => $entity->computed_price,
                'stock_status' => $entity->stock_status?->value ?? $entity->stock_status,
                'is_active' => $entity->is_active,
                'product' => $entity->relationLoaded('product') && $entity->product ? [
                    'id' => $entity->product->id,
                    'uuid' => $entity->product->uuid,
                    'name' => $entity->product->name,
                    'sku' => $entity->product->sku,
                    'is_active' => $entity->product->is_active,
                ] : null,
            ];
        }

        if ($entity instanceof ProductUom) {
            return [
                'type' => 'product_uom',
                'id' => $entity->id,
                'product_id' => $entity->product_id,
                'uom_id' => $entity->uom_id,
                'is_base_uom' => $entity->is_base_uom,
                'is_sales_uom' => $entity->is_sales_uom,
                'conversion_to_base' => $entity->conversion_to_base,
                'product' => $entity->relationLoaded('product') && $entity->product ? [
                    'id' => $entity->product->id,
                    'uuid' => $entity->product->uuid,
                    'name' => $entity->product->name,
                    'sku' => $entity->product->sku,
                    'is_active' => $entity->product->is_active,
                ] : null,
                'uom' => $entity->relationLoaded('uom') && $entity->uom ? [
                    'id' => $entity->uom->id,
                    'code' => $entity->uom->code,
                    'name' => $entity->uom->name,
                ] : null,
            ];
        }

        return null;
    }
}
