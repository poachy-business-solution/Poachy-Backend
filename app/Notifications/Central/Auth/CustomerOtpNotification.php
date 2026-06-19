<?php

namespace App\Notifications\Central\Auth;

use App\Mail\Central\Auth\CustomerOtpMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $otpCode,
        public readonly string $type,
        public readonly int    $expiresInMinutes = 10,
    ) {
        $this->onQueue('sync-normal');
    }

    /**
     * Delivery channels.
     *
     * Only 'mail' is active. The 'sms' channel is listed as a placeholder —
     * when an SMS gateway (e.g. Africa's Talking) is integrated, uncomment it
     * and register a custom SmsChannel in a service provider.
     */
    public function via(object $notifiable): array
    {
        $channels = ['mail'];

        // TODO: Uncomment when SMS gateway is integrated
        // if ($notifiable->marketplaceCustomer?->phone) {
        //     $channels[] = 'sms';
        // }

        return $channels;
    }

    /**
     * Email — delegates to the dedicated Mailable for full template control.
     */
    public function toMail(object $notifiable): CustomerOtpMail
    {
        return (new CustomerOtpMail(
            otpCode:          $this->otpCode,
            userName:         $notifiable->name,
            type:             $this->type,
            expiresInMinutes: $this->expiresInMinutes,
        ))->to($notifiable->email, $notifiable->name);
    }

    /**
     * SMS — placeholder until gateway is wired up.
     * A custom SmsChannel will call toSms() on this notification.
     */
    public function toSms(object $notifiable): string
    {
        $label   = $this->typeLabel();
        $message = "Your Poachy {$label} code is: {$this->otpCode}. Valid for {$this->expiresInMinutes} minutes. Do not share.";

        return $message;
    }

    // -------------------------------------------------------------------------

    private function typeLabel(): string
    {
        return match ($this->type) {
            'login'            => 'login verification',
            'password_reset'   => 'password reset',
            'verify_email'     => 'email verification',
            'verify_phone'     => 'phone verification',
            'update_password'  => 'password change confirmation',
            default            => 'verification',
        };
    }
}
