<?php

namespace App\Jobs\Tenant;

use App\Mail\Tenant\NotificationMail;
use App\Services\Tenant\Notifications\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $channel,
        public string $recipient,
        public string|array $message,
        public array $metadata = []
    ) {}

    /**
     * Execute the job.
     */
    public function handle(PushNotificationService $pushNotificationService): void
    {
        try {
            match ($this->channel) {
                'sms' => $this->sendSms(),
                'email' => $this->sendEmail(),
                'push' => $this->sendPush($pushNotificationService),
                default => throw new \InvalidArgumentException("Unsupported channel: {$this->channel}"),
            };

            Log::info('Notification sent', [
                'tenant_id' => tenant()?->id,
                'channel' => $this->channel,
                'recipient' => $this->recipient,
                'metadata' => $this->metadata,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification', [
                'tenant_id' => tenant()?->id,
                'channel' => $this->channel,
                'recipient' => $this->recipient,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Send SMS notification
     */
    protected function sendSms(): void
    {
        // Implement SMS provider integration (e.g., Africa's Talking, Twilio)
        // For now, log the SMS
        Log::info('SMS sent', [
            'tenant_id' => tenant()?->id,
            'to' => $this->recipient,
            'message' => $this->message,
        ]);

        // TODO: Integrate with SMS provider
        // Example:
        // SMS::send($this->recipient, $this->message);
    }

    /**
     * Send email notification
     */
    protected function sendEmail(): void
    {
        $subject = is_array($this->message) ? $this->message['subject'] : 'Notification';
        $body = is_array($this->message) ? $this->message['body'] : $this->message;

        Mail::to($this->recipient)->send(new NotificationMail(
            subjectLine: (string) $subject,
            body: (string) $body,
            notificationMetadata: $this->metadata,
        ));

        Log::info('Email sent', [
            'tenant_id' => tenant()?->id,
            'to' => $this->recipient,
            'subject' => $subject,
        ]);
    }

    /**
     * Send push notification
     */
    protected function sendPush(PushNotificationService $pushNotificationService): void
    {
        $sentCount = $pushNotificationService->send($this->recipient, $this->message, $this->metadata);

        Log::info('Push notification dispatched', [
            'tenant_id' => tenant()?->id,
            'to' => $this->recipient,
            'device_count' => $sentCount,
        ]);
    }
}
