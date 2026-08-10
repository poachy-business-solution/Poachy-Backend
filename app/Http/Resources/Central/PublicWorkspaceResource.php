<?php

namespace App\Http\Resources\Central;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicWorkspaceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $primaryDomain = $this->domains->firstWhere('is_primary', true)
            ?? $this->domains->first();

        return [
            'tenant_id' => $this->id,
            'name' => $this->businessDetail?->business_name
                ?? $this->tenant_name
                ?? $primaryDomain?->domain,
            'domain' => $primaryDomain?->domain,
            'login_url' => $primaryDomain ? 'https://'.$primaryDomain->domain.'/login' : null,
            'business' => [
                'status' => $this->businessDetail?->status,
                'city' => $this->businessDetail?->city,
                'county' => $this->businessDetail?->county,
                'is_verified' => $this->businessDetail?->is_verified ?? false,
                'logo_url' => $this->businessDetail?->business_logo
                    ? asset('storage/'.$this->businessDetail->business_logo)
                    : null,
            ],
        ];
    }
}
