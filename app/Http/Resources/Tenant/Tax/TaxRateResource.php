<?php

namespace App\Http\Resources\Tenant\Tax;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tax_name' => $this->tax_name,
            'rate' => number_format((float) $this->rate, 2, '.', ''),
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'effective_until' => $this->effective_until?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_currently_effective' => $this->isCurrentlyEffective(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
