<?php

namespace App\Mail\Central\Business;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BusinessRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ownerName,
        public string $businessName,
        public ?string $notes = null,
    ) {
        $this->onQueue('default');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Business Submission Needs Updates',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.business-rejected',
        );
    }
}
