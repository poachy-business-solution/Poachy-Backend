@extends('emails.layouts.app')

@section('title', 'Poachy Account Created')

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    Your Poachy Account Has Been Created!
</h2>

<p style="margin-bottom: 1rem; color: #4b5563;">
    Hello <strong>{{ $userName }}</strong>,
</p>

<p style="margin-bottom: 1.5rem; color: #4b5563;">
    A new account has been created for you on Poachy. You've been assigned the role of <strong>{{ ucfirst($role) }}</strong>. Below are your login credentials to access the system.
</p>

<!-- Credentials Box -->
<div style="background-color: #eff6ff; padding: 1.5rem; border-radius: 0.5rem; margin: 1.5rem 0;">
    <h3 style="color: #2563eb; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Your Login Credentials
    </h3>

    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 0.5rem 0; color: #4b5563; width: 40%;">
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
                <strong>Your Role:</strong>
            </td>
            <td style="padding: 0.5rem 0; color: #111827;">
                {{ ucfirst($role) }}
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
<div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin: 0; color: #991b1b; font-size: 0.875rem;">
        <strong>🔒 Security Important:</strong> Please change your password immediately after your first login. Do not share your credentials with anyone.
    </p>
</div>

<!-- Next Steps -->
<div style="margin-top: 1.5rem;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Getting Started:
    </h3>

    <ol style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8;">
        <li style="margin-bottom: 0.5rem;">Click the login link above or visit the login page</li>
        <li style="margin-bottom: 0.5rem;">Enter your email and temporary password</li>
        <li style="margin-bottom: 0.5rem;">Change your password when prompted</li>
        <li style="margin-bottom: 0.5rem;">Complete your profile if needed</li>
        <li style="margin-bottom: 0.5rem;">Start using Poachy!</li>
    </ol>
</div>

<!-- Support -->
<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 1.5rem 0;">

<p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
    Questions? Contact our support team at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection