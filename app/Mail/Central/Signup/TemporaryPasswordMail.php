<?php

namespace App\Mail\Central\Signup;

use App\Mail\PoachyMail;

/** The one-time password for the owner of a shop opened by Poachy staff. */
class TemporaryPasswordMail extends PoachyMail
{
    public function __construct(public string $ownerName, public string $workspace, public string $email, public string $password)
    {
        $this->onQueue('default');
    }

    protected function subjectLine(): string
    {
        return 'Your temporary Poachy password';
    }

    protected function markdownView(): string
    {
        return 'mail.signup.temporary-password';
    }
}
