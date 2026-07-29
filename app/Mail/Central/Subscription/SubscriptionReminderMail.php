<?php

namespace App\Mail\Central\Subscription;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionReminderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public string $businessName,
        public string $planName,
        public float $amountDue,
        public string $expiryDate,
        public int $daysRemaining,
        public string $paybillShortcode,
        public string $paybillAccountNumber,
    ) {
        $this->onQueue('sync-low');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->daysRemaining <= 1
                ? 'Final Notice: Your Poachy Subscription Expires Tomorrow'
                : "Your Poachy Subscription Expires in {$this->daysRemaining} Days",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.central.subscription-reminder',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
