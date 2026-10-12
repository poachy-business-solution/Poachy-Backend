<?php

namespace App\Mail\Central\Signup;

use App\Mail\PoachyMail;

class SignupCodeMail extends PoachyMail
{
    public function __construct(public string $ownerName, public string $code, public int $minutes)
    {
        $this->onQueue('default');
    }

    protected function subjectLine(): string
    {
        return "Your Poachy sign-up code: {$this->code}";
    }

    protected function markdownView(): string
    {
        return 'mail.signup.code';
    }
}
