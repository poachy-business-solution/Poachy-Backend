<?php

namespace App\Exceptions;

class CardPaymentException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $providerErrorCode = '',
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
