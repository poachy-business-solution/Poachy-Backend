<?php

namespace App\Http\Resources\Tenant\Sales;

use App\Services\Tenant\Sales\CreditService;
use App\Services\Tenant\Sales\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSearchResource extends JsonResource
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
            'phone' => $this->phone,
            'email' => $this->email,
            'customer_type' => $this->customer_type->value,
            'customer_type_label' => $this->customer_type->label(),
            'loyalty' => [
                'enabled' => app(LoyaltyService::class)->isEnabled(),
                'balance' => (float) $this->loyalty_points,
                'expiring_soon' => $this->when(
                    app(LoyaltyService::class)->isEnabled(),
                    function () {
                        return (float) app(LoyaltyService::class)
                            ->getExpiringPoints($this->resource, 30);
                    },
                    0
                ),
                'expiring_in_days' => 30,
            ],
            'credit' => [
                'enabled' => app(CreditService::class)->isEnabled(),
                'credit_limit' => (float) $this->credit_limit,
                'current_debt' => (float) $this->current_debt,
                'available_credit' => (float) $this->available_credit,
                'is_overdue' => $this->when(
                    app(CreditService::class)->isEnabled(),
                    function () {
                        $summary = app(CreditService::class)
                            ->getCreditSummary($this->resource);

                        return $summary['is_overdue'] ?? false;
                    },
                    false
                ),
            ],
            'total_lifetime_purchases' => (float) $this->total_lifetime_purchases,
            'total_visits' => $this->total_visits,
            'is_active' => $this->is_active,
        ];
    }
}
