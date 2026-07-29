@extends('emails.layouts.app')

@section('title', 'Your Subscription Has Expired')

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🔒</div>
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Hey {{ $businessName }}, Your Subscription Has Expired
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563; text-align: center; font-size: 1.125rem;">
    Your <strong>{{ $planName }}</strong> plan expired on <strong>{{ $expiredDate }}</strong> and your account access is now restricted. Renew now to get back up and running.
</p>

<!-- Subscription Summary -->
<div style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #991b1b; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Renewal Required
    </h3>

    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #fecaca;">
        <p style="margin: 0; color: #111827;">Plan</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $planName }}</p>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; margin-top: 0.5rem;">
        <p style="margin: 0; font-weight: 700; color: #111827; font-size: 1.125rem;">Renewal Amount:</p>
        <p style="margin: 0; font-weight: 700; color: #2563eb; font-size: 1.25rem;">KES {{ number_format($planPrice, 2) }}</p>
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
