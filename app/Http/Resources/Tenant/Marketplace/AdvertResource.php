<?php

namespace App\Http\Resources\Tenant\Marketplace;

use App\Models\Advert;
use App\Services\Central\Marketplace\AdvertService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An advert with its results, for the shop (web business account and the app).
 *
 * @mixin Advert
 */
class AdvertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type_key,
            'type_name' => $this->type?->name,
            'product' => $this->product ? ['id' => $this->product->id, 'name' => $this->product->name, 'slug' => $this->product->slug] : null,
            'county' => $this->county,
            'price' => (float) $this->price,
            'days' => $this->days,
            'status' => $this->status,
            'paid_with' => $this->paid_with,
            'billing_topup_id' => $this->billing_topup_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'views' => $this->views,
            'clicks' => $this->clicks,
            'orders' => app(AdvertService::class)->ordersFor($this->resource),
            'stopped_reason' => $this->stopped_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
