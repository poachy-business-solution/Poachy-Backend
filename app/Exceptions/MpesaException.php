<?php

namespace App\Exceptions;

class MpesaException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $darajaErrorCode = '',
        public readonly array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function oauthFailed(string $body): self
    {
        return new self(
            'Failed to obtain M-Pesa access token.',
            'OAUTH_FAILED',
            ['response_body' => $body],
        );
    }

    public static function stkPushFailed(string $error, array $context = []): self
    {
        return new self(
            $error ?: 'STK push request failed.',
            'STK_PUSH_FAILED',
            $context,
        );
    }

    public static function c2bRegistrationFailed(string $error): self
    {
        return new self(
            $error ?: 'C2B URL registration failed.',
            'C2B_REGISTRATION_FAILED',
        );
    }

    public static function invalidPhoneNumber(string $phone): self
    {
        return new self(
            "Invalid phone number: '{$phone}'. Kenyan mobile numbers in any standard format are accepted.",
            'INVALID_PHONE',
            ['phone' => $phone],
        );
    }
}
