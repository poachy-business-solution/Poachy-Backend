<?php

namespace App\Http\Resources\Tenant\Uom;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UomConversionResource extends JsonResource
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
            'from_uom' => new UnitOfMeasureResource($this->whenLoaded('fromUom')),
            'to_uom' => new UnitOfMeasureResource($this->whenLoaded('toUom')),
            'conversion_factor' => (float) $this->conversion_factor,
            'reverse_factor' => (float) $this->reverse_factor,
            'description' => $this->description,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
