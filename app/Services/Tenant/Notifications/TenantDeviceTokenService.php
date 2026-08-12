<?php

namespace App\Services\Tenant\Notifications;

use App\Models\Tenant\TenantDeviceToken;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Collection;

class TenantDeviceTokenService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(User $user, array $data): TenantDeviceToken
    {
        return TenantDeviceToken::updateOrCreate(
            ['token_hash' => $this->hashToken($data['token'])],
            [
                'user_id' => $user->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_id' => $data['device_id'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );
    }

    /**
     * @return Collection<int, TenantDeviceToken>
     */
    public function activeForUser(User $user): Collection
    {
        return $user->deviceTokens()
            ->active()
            ->latest('last_seen_at')
            ->get();
    }

    public function revoke(User $user, string $token): int
    {
        return TenantDeviceToken::where('user_id', $user->id)
            ->where('token_hash', $this->hashToken($token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
