@extends('emails.layouts.app')

@section('title', $daysRemaining <= 1 ? 'Your Subscription Expires Tomorrow' : 'Your Subscription Expires Soon')

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">⏰</div>
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        @if($daysRemaining <= 1)
            Final Notice, {{ $businessName }}!
        @else
            Hey {{ $businessName }}, Your Subscription Expires Soon
        @endif
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563; text-align: center; font-size: 1.125rem;">
    @if($daysRemaining <= 1)
        Your <strong>{{ $planName }}</strong> plan expires <strong>tomorrow</strong> ({{ $expiryDate }}). Renew now to avoid losing access to your account.
    @else
        Your <strong>{{ $planName }}</strong> plan expires in <strong>{{ $daysRemaining }} days</strong> ({{ $expiryDate }}). Renew now to keep your account running without interruption.
    @endif
</p>

<!-- Subscription Summary -->
<div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Subscription Details
    </h3>

    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
        <p style="margin: 0; color: #111827;">Plan</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $planName }}</p>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
        <p style="margin: 0; color: #111827;">Expiry Date</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $expiryDate }}</p>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; margin-top: 0.5rem;">
        <p style="margin: 0; font-weight: 700; color: #111827; font-size: 1.125rem;">Amount Due:</p>
        <p style="margin: 0; font-weight: 700; color: #2563eb; font-size: 1.25rem;">KES {{ number_format($amountDue, 2) }}</p>
    </div>
</div>

<!-- Paybill Instructions -->
<div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 1.5rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <h3 style="color: #1e40af; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        💳 Renew via M-Pesa Paybill
    </h3>
    <p style="margin: 0 0 0.5rem 0; color: #1e3a8a;">Paybill Number: <strong>{{ $paybillShortcode }}</strong></p>
    <p style="margin: 0; color: #1e3a8a;">Account Number: <strong>{{ $paybillAccountNumber }}</strong></p>
</div>

<p style="margin-top: 2rem; color: #6b7280; font-size: 0.875rem; text-align: center;">
    Questions? Contact us at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection
