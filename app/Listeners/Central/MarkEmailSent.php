<?php

namespace App\Listeners\Central;

use App\Models\MessageLog;
use App\Services\Shared\Messaging\Messenger;
use Illuminate\Mail\Events\MessageSent;

/**
 * Flips an email's message log row to "sent" once the mailer has handed it over.
 */
class MarkEmailSent
{
    public function handle(MessageSent $event): void
    {
        $id = $event->message->getHeaders()->get(Messenger::LOG_HEADER)?->getBodyAsString();

        if ($id !== null && ctype_digit($id)) {
            MessageLog::whereKey((int) $id)->update(['status' => 'sent']);
        }
    }
}
