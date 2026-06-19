<?php

namespace App\Http\Resources\Tenant\Supplier;

use App\Http\Resources\Tenant\Product\ProductMinimalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            // Supplier type 
            'supplier_type' => $this->supplier_type?->value,
            'supplier_type_display' => $this->supplier_type?->displayName(),
            'supplier_type_description' => $this->supplier_type?->description(),

            // Contact information
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'registration_number' => $this->registration_number,

            // Financial details
            'credit_limit' => number_format((float) $this->credit_limit, 2, '.', ''),
            'outstanding_balance' => number_format((float) $this->outstanding_balance, 2, '.', ''),

            // Payment terms with enum details
            'payment_terms' => $this->payment_terms?->value,
            'payment_terms_display' => $this->payment_terms?->displayName(),
            'payment_terms_description' => $this->payment_terms?->description(),
            'payment_terms_days' => $this->payment_terms?->days(),

            'bank_account_details' => $this->bank_account_details,

            // Ratings & metrics
            'rating' => number_format((float) $this->rating, 2, '.', ''),
            'total_orders' => $this->total_orders,

            // Status
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            // Relationships
            'products' => $this->when(
                $this->relationLoaded('products'),
                fn() => ProductMinimalResource::collection($this->products)
            ),

            'product_count' => $this->when(
                $this->relationLoaded('products'),
                fn() => $this->products->count()
            ),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
