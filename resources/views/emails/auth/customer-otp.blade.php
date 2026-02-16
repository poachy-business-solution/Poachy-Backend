@extends('emails.layouts.app')

@section('title', $heading)

@section('content')
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
        {{ $heading }}
    </h2>

    <p style="margin-bottom: 1rem; color: #4b5563;">
        Hello <strong>{{ $userName }}</strong>,
    </p>

    <p style="margin-bottom: 1.5rem; color: #4b5563;">
        {{ $bodyText }}
    </p>

    <!-- OTP Code Box -->
    <div style="background-color: #eff6ff; padding: 2rem; text-align: center; border-radius: 0.5rem; margin: 1.5rem 0;">
        <div
            style="color: #2563eb; font-size: 2.25rem; font-weight: 700; letter-spacing: 0.5rem; margin: 0; font-family: 'Courier New', monospace;">
            {{ $otpCode }}
        </div>
    </div>

    <p style="margin-bottom: 1rem; color: #4b5563;">
        <strong>This code will expire in {{ $expiresInMinutes }} minutes.</strong>
    </p>

    @if (in_array($type, ['login', 'password_reset', 'update_password']))
        <div
            style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
            <p style="margin: 0; color: #991b1b; font-size: 0.875rem;">
                <strong>Security Notice:</strong>
                @if ($type === 'login')
                    If you did not attempt to log in, please ignore this email or contact our support team immediately.
                @elseif($type === 'password_reset')
                    If you did not request a password reset, please ignore this email. Your account remains safe.
                @elseif($type === 'update_password')
                    If you did not request this change, please contact our support team immediately.
                @endif
            </p>
        </div>
    @endif

    <p style="color: #6b7280; font-size: 0.875rem; margin-top: 1.5rem;">
        Never share this code with anyone, including Poachy staff.
    </p>
@endsection
