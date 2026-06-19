@extends('emails.layouts.app')

@section('title', 'Welcome to Poachy')

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    Welcome to Poachy!
</h2>

<p style="margin-bottom: 1rem; color: #4b5563;">
    Hello <strong>{{ $userName }}</strong>,
</p>

<p style="margin-bottom: 1.5rem; color: #4b5563;">
    Your Poachy merchant account has been created successfully! You can now access your dashboard and complete your business profile.
</p>

<!-- Credentials Box -->
<div style="background-color: #eff6ff; padding: 1.5rem; border-radius: 0.5rem; margin: 1.5rem 0;">
    <h3 style="color: #2563eb; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Your Login Credentials
    </h3>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0.5rem 0; color: #4b5563;">
                <strong>Email:</strong>
            </td>
            <td style="padding: 0.5rem 0; color: #111827;">
                {{ $email }}
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #4b5563; vertical-align: top;">
                <strong>Temporary Password:</strong>
            </td>
            <td style="padding: 0.5rem 0;">
                <code style="background-color: #ffffff; padding: 0.5rem 0.75rem; border-radius: 0.375rem; color: #111827; font-family: 'Courier New', monospace; font-size: 0.9rem; display: inline-block;">{{ $password }}</code>
            </td>
        </tr>
        <tr>
            <td style="padding: 0.5rem 0; color: #4b5563; vertical-align: top;">
                <strong>Login URL:</strong>
            </td>
            <td style="padding: 0.5rem 0;">
                <a href="{{ $loginUrl }}" style="color: #2563eb; text-decoration: none; word-break: break-all;">{{ $loginUrl }}</a>
            </td>
        </tr>
    </table>
</div>

<!-- Security Warning -->
<div style="background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin: 0; color: #92400e; font-size: 0.875rem;">
        <strong>⚠️ Important:</strong> Please change your password after your first login for security purposes.
    </p>
</div>

<!-- Next Steps -->
<div style="margin-top: 1.5rem;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Next Steps:
    </h3>

    <ol style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8;">
        <li style="margin-bottom: 0.5rem;">Click the login link above or visit your merchant portal</li>
        <li style="margin-bottom: 0.5rem;">Log in with the provided credentials</li>
        <li style="margin-bottom: 0.5rem;">Complete your business profile</li>
        <li style="margin-bottom: 0.5rem;">Wait for admin approval</li>
        <li style="margin-bottom: 0.5rem;">Start selling!</li>
    </ol>
</div>

<!-- Support -->
<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0;">

<p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
    Need help? Contact our support team at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection