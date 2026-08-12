<?php

namespace App\Services\Tenant\Notifications;

use App\Models\Tenant\TenantDeviceToken;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function __construct(
        private readonly TenantDeviceTokenService $deviceTokenService
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function send(string $recipient, string|array $message, array $metadata = []): int
    {
        $tokens = $this->resolveTokens($recipient, $metadata);

        if ($tokens->isEmpty()) {
            Log::info('Push notification skipped; no active device tokens', [
                'tenant_id' => tenant()?->id,
                'recipient' => $recipient,
                'metadata' => $metadata,
            ]);

            return 0;
        }

        $payload = $this->payload($message, $metadata);

        foreach ($tokens as $token) {
            Log::info('Push notification queued for provider', [
                'tenant_id' => tenant()?->id,
                'user_id' => $token->user_id,
                'device_token_id' => $token->id,
                'platform' => $token->platform,
                'title' => $payload['title'],
                'body' => $payload['body'],
                'data' => $payload['data'],
            ]);
        }

        return $tokens->count();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, TenantDeviceToken>
     */
    private function resolveTokens(string $recipient, array $metadata): Collection
    {
        if (isset($metadata['user_id'])) {
            return TenantDeviceToken::active()
                ->where('user_id', (int) $metadata['user_id'])
                ->get();
        }

        $user = filter_var($recipient, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $recipient)->first()
            : null;

        if ($user) {
            return $this->deviceTokenService->activeForUser($user);
        }

        return TenantDeviceToken::active()
            ->where('token_hash', $this->deviceTokenService->hashToken($recipient))
            ->get();
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{title: string, body: string, data: array<string, string>}
     */
    private function payload(string|array $message, array $metadata): array
    {
        $title = is_array($message)
            ? (string) ($message['title'] ?? $message['subject'] ?? 'Poachy')
            : 'Poachy';

        $body = is_array($message)
            ? (string) ($message['body'] ?? '')
            : $message;

        return [
            'title' => $title,
            'body' => $body,
            'data' => collect($metadata)
                ->map(fn (mixed $value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value))
                ->all(),
        ];
    }
}
