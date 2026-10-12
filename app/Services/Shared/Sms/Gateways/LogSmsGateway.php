<?php

namespace App\Services\Shared\Sms\Gateways;

use App\Services\Shared\Sms\SmsGateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes each SMS to storage/logs/sms.log instead of sending it. Local development reads
 * codes from here (e.g. sign-up phone checks in Playwright).
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): array
    {
        $id = 'log-'.Str::lower(Str::random(10));

        Log::channel('sms')->info('SMS', ['id' => $id, 'to' => $to, 'message' => $message]);

        return ['id' => $id, 'status' => 'logged'];
    }
}
