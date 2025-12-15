<?php

namespace App\Http\Resources\Tenant\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'region' => $this->region,
            'phone' => $this->phone,
            'email' => $this->email,
            'is_main_store' => $this->is_main_store,
            'is_active' => $this->is_active,
            'status_label' => $this->status_label,
            'store_type_label' => $this->store_type_label,

            // Relationships
            'manager' => $this->when(
                $this->relationLoaded('manager'),
                fn() => $this->manager ? [
                    'id' => $this->manager->id,
                    'name' => $this->manager->name,
                    'email' => $this->manager->email,
                    'phone' => $this->manager->phone ?? null,
                ] : null
            ),

            'creator' => $this->when(
                $this->relationLoaded('creator'),
                fn() => $this->creator ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ] : null
            ),

            'updater' => $this->when(
                $this->relationLoaded('updater'),
                fn() => $this->updater ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                ] : null
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->when(
                !is_null($this->deleted_at),
                $this->deleted_at?->toISOString()
            ),
        ];
    }
}
