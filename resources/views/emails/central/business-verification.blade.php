@extends('emails.layouts.app')

@section('title', $isVerified ? 'Business Verification Approved' : 'Business Verification Update')

@section('content')
@if($isVerified)
{{-- APPROVED VERSION --}}
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">✅</div>
    <h2 style="color: #059669; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Business Verification Approved!
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563;">
    Hello <strong>{{ $ownerName }}</strong>,
</p>

<p style="margin-bottom: 1.5rem; color: #4b5563;">
    Great news! Your business <strong>{{ $businessName }}</strong> has successfully passed our verification process and is now verified on Poachy.
</p>

<!-- Success Box -->
<div style="background-color: #d1fae5; border-left: 4px solid #10b981; padding: 1.5rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin: 0 0 0.5rem 0; color: #065f46; font-weight: 600;">
        ✨ What This Means For You:
    </p>
    <ul style="margin: 0; padding-left: 1.5rem; color: #064e3b; line-height: 1.8;">
        <li style="margin-bottom: 0.25rem;">Your business profile displays a verified badge</li>
        <li style="margin-bottom: 0.25rem;">Increased customer trust and credibility</li>
        <li style="margin-bottom: 0.25rem;">Better visibility in search results</li>
        <li style="margin-bottom: 0.25rem;">Access to premium features</li>
    </ul>
</div>

<!-- Next Steps -->
<div style="margin-top: 2rem;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        🚀 What's Next?
    </h3>
    <ol style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8;">
        <li style="margin-bottom: 0.5rem;">Continue building your product catalog</li>
        <li style="margin-bottom: 0.5rem;">Promote your verified business</li>
        <li style="margin-bottom: 0.5rem;">Engage with your customers</li>
        <li style="margin-bottom: 0.5rem;">Monitor your analytics and performance</li>
    </ol>
</div>

@else
{{-- NOT VERIFIED / REJECTED VERSION --}}
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">⚠️</div>
    <h2 style="color: #dc2626; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Business Verification Update
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563;">
    Hello <strong>{{ $ownerName }}</strong>,
</p>

<p style="margin-bottom: 1.5rem; color: #4b5563;">
    We've reviewed your business <strong>{{ $businessName }}</strong> and need some additional information or corrections before we can complete the verification process.
</p>

<!-- Help Section -->
<div style="background-color: #eff6ff; padding: 1.25rem; margin: 2rem 0; border-radius: 0.5rem;">
    <p style="margin: 0; color: #1e40af; font-size: 0.875rem;">
        <strong>💡 Need Help?</strong> Our support team is here to assist you with the verification process. Contact us at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
    </p>
</div>
@endif

<!-- Closing -->
<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 2rem 0;">

<p style="margin: 0 0 0.5rem 0; color: #4b5563;">
    @if($isVerified)
    Thank you for being part of the Poachy community!
    @else
    We look forward to completing your verification soon.
    @endif
</p>

<p style="margin: 0; color: #4b5563;">
    <strong>Best regards,</strong><br>
    The {{ config('app.name') }} Team
</p>
@endsection