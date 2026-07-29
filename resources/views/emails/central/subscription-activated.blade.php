@extends('emails.layouts.app')

@section('title', 'Subscription Activated')

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">✅</div>
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Thanks, {{ $businessName }} — Payment Received!
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563; text-align: center; font-size: 1.125rem;">
    Your payment was successful and your <strong>{{ $planName }}</strong> subscription is now active.
</p>

<!-- Payment Summary -->
<div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #166534; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Payment Summary
    </h3>

    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #bbf7d0;">
        <p style="margin: 0; color: #111827;">Plan</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $planName }}</p>
    </div>
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #bbf7d0;">
        <p style="margin: 0; color: #111827;">Payment Method</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $paymentMethod }}</p>
    </div>
    @if($renewalDate)
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #bbf7d0;">
        <p style="margin: 0; color: #111827;">Renews On</p>
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $renewalDate }}</p>
    </div>
    @endif
    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; margin-top: 0.5rem;">
        <p style="margin: 0; font-weight: 700; color: #111827; font-size: 1.125rem;">Amount Paid:</p>
        <p style="margin: 0; font-weight: 700; color: #16a34a; font-size: 1.25rem;">KES {{ number_format($amountPaid, 2) }}</p>
    </div>
</div>

<p style="margin-top: 2rem; color: #6b7280; font-size: 0.875rem; text-align: center;">
    Questions? Contact us at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection
