<?php

namespace App\Http\Resources\Tenant\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductScaleBarcodeFormatResource extends JsonResource
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
            'name' => $this->name,
            'prefix' => $this->prefix,
            'length' => $this->length,
            'product_code_start' => $this->product_code_start,
            'product_code_length' => $this->product_code_length,
            'value_start' => $this->value_start,
            'value_length' => $this->value_length,
            'value_type' => $this->value_type,
            'decimal_places' => $this->decimal_places,
            'checksum' => $this->checksum,
            'store_id' => $this->store_id,
            'is_active' => $this->is_active,
            'priority' => $this->priority,
            'metadata' => $this->metadata ?? [],
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
