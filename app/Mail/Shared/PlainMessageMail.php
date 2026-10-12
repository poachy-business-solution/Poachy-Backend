<?php

namespace App\Mail\Shared;

use App\Mail\PoachyMail;

/**
 * A short branded message: heading, paragraphs, optional button and optional Paybill box.
 * Used for one-off notices and as the reference example of the Poachy mail layout.
 */
class PlainMessageMail extends PoachyMail
{
    /**
     * @param  array<int, string>  $paragraphs
     */
    public function __construct(
        public string $heading,
        public array $paragraphs,
        public ?string $buttonLabel = null,
        public ?string $buttonUrl = null,
        public ?string $paybillAccount = null,
        public ?string $payUrl = null,
    ) {
        $this->onQueue('default');
    }

    protected function subjectLine(): string
    {
        return $this->heading;
    }

    protected function markdownView(): string
    {
        return 'mail.plain-message';
    }
}
