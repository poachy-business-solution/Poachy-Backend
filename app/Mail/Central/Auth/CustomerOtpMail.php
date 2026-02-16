<?php

namespace App\Mail\Central\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $otpCode,
        public readonly string $userName,
        public readonly string $type,
        public readonly int    $expiresInMinutes = 10,
    ) {
        $this->onQueue('sync-normal');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auth.customer-otp',
            with: [
                'otpCode'          => $this->otpCode,
                'userName'         => $this->userName,
                'type'             => $this->type,
                'expiresInMinutes' => $this->expiresInMinutes,
                'heading'          => $this->heading(),
                'bodyText'         => $this->bodyText(),
            ],
        );
    }

    // -------------------------------------------------------------------------

    private function subjectLine(): string
    {
        return match ($this->type) {
            'login'            => 'Your Poachy Login Code',
            'password_reset'   => 'Reset Your Poachy Password',
            'verify_email'     => 'Verify Your Email Address',
            'verify_phone'     => 'Verify Your Phone Number',
            'update_password'  => 'Confirm Your Password Change',
            default            => 'Your Poachy Verification Code',
        };
    }

    private function heading(): string
    {
        return match ($this->type) {
            'login'            => 'Login Verification',
            'password_reset'   => 'Password Reset',
            'verify_email'     => 'Email Verification',
            'verify_phone'     => 'Phone Verification',
            'update_password'  => 'Confirm Password Change',
            default            => 'Verification Code',
        };
    }

    private function bodyText(): string
    {
        return match ($this->type) {
            'login'            => 'You are attempting to log in to Poachy. Use the code below to complete your login:',
            'password_reset'   => 'We received a request to reset your Poachy password. Use the code below to proceed:',
            'verify_email'     => 'Please use the code below to verify your email address:',
            'verify_phone'     => 'Please use the code below to verify your phone number:',
            'update_password'  => 'You requested to change your password. Enter the code below to confirm:',
            default            => 'Use the verification code below:',
        };
    }
}