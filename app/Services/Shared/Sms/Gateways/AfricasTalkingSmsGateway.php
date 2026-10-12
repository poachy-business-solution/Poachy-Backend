<?php

namespace App\Services\Shared\Sms\Gateways;

use App\Exceptions\SmsException;
use App\Services\Shared\Sms\SmsGateway;
use Illuminate\Support\Facades\Http;

/**
 * Africa's Talking bulk SMS (POST /version1/messaging). Only used when SMS_DRIVER=africastalking.
 */
class AfricasTalkingSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        $config = config('messaging.sms.africastalking');

        $response = Http::asForm()
            ->acceptJson()
            ->withHeaders(['apiKey' => (string) $config['api_key']])
            ->timeout(15)
            ->post(rtrim($config['base_url'], '/').'/version1/messaging', array_filter([
                'username' => $config['username'],
                'to' => $to,
                'message' => $message,
                'from' => config('messaging.sms.sender_id') ?: null,
            ]));

        $recipient = $response->json('SMSMessageData.Recipients.0');

        if (! $response->successful() || $recipient === null || ! in_array((int) ($recipient['statusCode'] ?? 0), [100, 101, 102], true)) {
            throw new SmsException('SMS was not accepted: '.($recipient['status'] ?? $response->json('SMSMessageData.Message') ?? $response->status()));
        }

        return ['id' => $recipient['messageId'] ?? null, 'status' => $recipient['status'] ?? 'Success'];
    }
}
