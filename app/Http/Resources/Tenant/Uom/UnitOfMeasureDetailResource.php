<?php

namespace App\Http\Resources\Tenant\Uom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitOfMeasureDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'source_type' => $this->source_type->value,
            'source_type_label' => $this->source_type->label(),
            'is_base_unit' => $this->is_base_unit,
            'is_active' => $this->is_active,
            'is_system' => $this->isSystem(),
            'is_custom' => $this->isCustom(),
            'description' => $this->description,
            'display_name' => $this->display_name,

            // Include base unit information
            'base_unit' => $this->when(
                !$this->is_base_unit,
                fn() => new UnitOfMeasureResource($this->getBaseUnit())
            ),

            // Conversions from this UOM to others
            'conversions_from' => UomConversionResource::collection(
                $this->whenLoaded('conversionsFrom')
            ),

            // Conversions to this UOM from others
            'conversions_to' => UomConversionResource::collection(
                $this->whenLoaded('conversionsTo')
            ),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
