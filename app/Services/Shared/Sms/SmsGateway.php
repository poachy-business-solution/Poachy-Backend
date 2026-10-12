<?php

namespace App\Services\Shared\Sms;

use App\Exceptions\SmsException;

/**
 * Sends a text message. Selected by `config('messaging.sms.driver')`: `log` writes to
 * storage/logs/sms.log (local + tests), `africastalking` calls Africa's Talking.
 */
interface SmsGateway
{
    /**
     * @param  string  $to  Phone in E.164 (+2547XXXXXXXX)
     * @return array{id: string|null, status: string}
     *
     * @throws SmsException
     */
    public function send(string $to, string $message): array;
}
