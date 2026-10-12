<?php

namespace App\Jobs\Central;

use App\Models\MessageLog;
use App\Services\Shared\Sms\SmsGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Sends one SMS through the configured gateway and records the outcome on its message log row.
 */
class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public int $messageLogId, public string $to, public string $message)
    {
        $this->onQueue('default');
    }

    public function handle(SmsGateway $sms): void
    {
        $result = $sms->send($this->to, $this->message);

        MessageLog::whereKey($this->messageLogId)->update([
            'status' => 'sent',
            'provider_id' => $result['id'],
            'error' => null,
        ]);
    }

    public function failed(Throwable $e): void
    {
        MessageLog::whereKey($this->messageLogId)->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
    }
}
