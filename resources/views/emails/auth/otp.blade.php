@extends('emails.layouts.app')

@section('title', 'Login Verification Code')

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    Login Verification
</h2>

<p style="margin-bottom: 1rem; color: #4b5563;">
    Hello <strong>{{ $userName }}</strong>,
</p>

<p style="margin-bottom: 1.5rem; color: #4b5563;">
    You are attempting to log in to the Poachy. Please use the verification code below to complete your login:
</p>

<!-- OTP Code Box -->
<div style="background-color: #eff6ff; padding: 2rem; text-align: center; border-radius: 0.5rem; margin: 1.5rem 0;">
    <div style="color: #2563eb; font-size: 2.25rem; font-weight: 700; letter-spacing: 0.5rem; margin: 0; font-family: 'Courier New', monospace;">
        {{ $otpCode }}
    </div>
</div>

<p style="margin-bottom: 1rem; color: #4b5563;">
    <strong>This code will expire in {{ $expiresInMinutes }} minutes.</strong>
</p>

<div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin: 0; color: #991b1b; font-size: 0.875rem;">
        <strong>Security Notice:</strong> If you did not attempt to log in, please ignore this email or contact support immediately.
    </p>
</div>
@endsection