<?php

namespace App\Http\Resources\Tenant\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'customer_number' => $this->customer_number,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'address' => $this->address,
            'customer_type' => [
                'value' => $this->customer_type->value,
                'label' => $this->customer_type->label(),
            ],
            'loyalty_points' => (float) $this->loyalty_points,
            'total_lifetime_purchases' => (float) $this->total_lifetime_purchases,
            'total_visits' => $this->total_visits,
            'credit_limit' => (float) $this->credit_limit,
            'current_debt' => (float) $this->current_debt,
            'available_credit' => (float) $this->available_credit,
            'is_active' => $this->is_active,
            'registered_at' => $this->registered_at?->toISOString(),
            'preferred_store' => $this->when(
                $this->relationLoaded('preferredStore'),
                fn() => [
                    'id' => $this->preferredStore?->id,
                    'name' => $this->preferredStore?->name,
                    'code' => $this->preferredStore?->code,
                ]
            ),
            'current_group' => $this->when(
                $this->relationLoaded('currentGroup'),
                fn() => $this->currentGroup?->group ? [
                    'id' => $this->currentGroup->group->id,
                    'name' => $this->currentGroup->group->name,
                    'discount_percentage' => (float) $this->currentGroup->group->discount_percentage,
                    'joined_at' => $this->currentGroup->joined_at->toISOString(),
                ] : null
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
