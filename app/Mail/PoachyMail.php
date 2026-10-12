<?php

namespace App\Mail;

use App\Services\Shared\Messaging\Messenger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/**
 * Base for Poachy's transactional emails: queued, markdown, Poachy theme
 * (resources/views/vendor/mail). Subclasses give a subject and a markdown view under
 * resources/views/mail/; public properties are available in the view.
 *
 * Send through {@see Messenger} so every send is logged.
 */
abstract class PoachyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** Set by the Messenger; sent as a header so the send log can be marked "sent". */
    public ?int $messageLogId = null;

    abstract protected function subjectLine(): string;

    abstract protected function markdownView(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(markdown: $this->markdownView());
    }

    public function headers(): Headers
    {
        return new Headers(text: $this->messageLogId === null ? [] : [Messenger::LOG_HEADER => (string) $this->messageLogId]);
    }

    /** The subject, for the send log. */
    public function subjectForLog(): string
    {
        return $this->subjectLine();
    }
}
