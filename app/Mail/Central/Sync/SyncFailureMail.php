<?php

namespace App\Mail\Central\Sync;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SyncFailureMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public string $syncType,
        public int|string $syncQueueId,
        public string $errorMessage,
        public array $details = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Poachy sync failed: {$this->syncType}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.sync-failure',
        );
    }
}
