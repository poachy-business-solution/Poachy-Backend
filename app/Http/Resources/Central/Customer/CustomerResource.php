<?php

namespace App\Http\Resources\Central\Customer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var \App\Models\MarketplaceCustomer $this */
        $user = $this->user; // Eager-loaded

        return [
            // ── Identity ─────────────────────────────────────────────────────
            'id'               => $this->id,
            'customer_number'  => $this->customer_number,

            // ── From User (auth record) ───────────────────────────────────────
            'name'             => $user->name,
            'email'            => $user->email,
            'email_verified'   => $user->email_verified_at !== null,

            // ── From MarketplaceCustomer (profile) ────────────────────────────
            'phone'            => $this->phone,
            'phone_verified'   => (bool) $this->phone_verified,
            'date_of_birth'    => $this->date_of_birth?->toDateString(),
            'gender'           => $this->gender,
            'profile_picture'  => $this->profile_picture,

            // ── Preferences ───────────────────────────────────────────────────
            'accepts_marketing' => (bool) $this->accepts_marketing,
            'accepts_sms'       => (bool) $this->accepts_sms,

            // ── Status ────────────────────────────────────────────────────────
            'is_active'        => (bool) $this->is_active,

            // ── Timestamps ────────────────────────────────────────────────────
            'last_login_at'    => $this->last_login_at?->toISOString(),
            'member_since'     => $this->created_at->toDateString(),
        ];
    }
}
