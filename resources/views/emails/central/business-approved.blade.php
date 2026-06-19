@extends('emails.layouts.app')

@section('title', 'Business Approved')

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🎉</div>
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Congratulations, {{ $ownerName }}!
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563; text-align: center; font-size: 1.125rem;">
    Your business <strong>{{ $businessName }}</strong> has been approved and is now active on Poachy!
</p>

<!-- Call to Action Button -->
<div style="text-align: center; margin: 2rem 0;">
    <a href="{{ $loginUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 0.875rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 1rem;">
        Access Your Dashboard
    </a>
</div>

<!-- What's Next Section -->
<div style="background-color: #f0fdf4; border-left: 4px solid #10b981; padding: 1.5rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <h3 style="color: #065f46; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        🚀 What's Next?
    </h3>
    <p style="margin: 0 0 1rem 0; color: #064e3b;">
        You can now start setting up your business:
    </p>
    <ol style="margin: 0; padding-left: 1.5rem; color: #064e3b; line-height: 1.8;">
        <li style="margin-bottom: 0.5rem;"><strong>Complete Your Profile</strong> - Add your logo, operating hours, and delivery information</li>
        <li style="margin-bottom: 0.5rem;"><strong>Choose a Subscription Plan</strong> - Select the plan that fits your needs</li>
        <li style="margin-bottom: 0.5rem;"><strong>Start Adding Products</strong> - Build your inventory and start selling</li>
    </ol>
</div>

<!-- Subscription Plans -->
<div style="margin-top: 2rem;">
    <h3 style="color: #111827; font-size: 1.25rem; font-weight: 700; margin-top: 0; margin-bottom: 1.5rem; text-align: center;">
        💼 Available Subscription Plans
    </h3>

    @foreach($subscriptionPlans as $plan)
    <div style="background-color: #ffffff; border: 2px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.75rem;">
            <h4 style="color: #2563eb; font-size: 1.125rem; font-weight: 600; margin: 0;">
                {{ $plan['name'] }}
            </h4>
            <span style="color: #111827; font-size: 1.25rem; font-weight: 700;">
                KES {{ number_format($plan['price'], 2) }}
            </span>
        </div>

        <p style="color: #6b7280; margin: 0 0 1rem 0; font-size: 0.875rem;">
            {{ $plan['description'] }}
        </p>

        <div style="border-top: 1px solid #e5e7eb; padding-top: 1rem; margin-top: 1rem;">
            <p style="color: #111827; font-weight: 600; margin: 0 0 0.5rem 0; font-size: 0.875rem;">
                Key Features:
            </p>
            <ul style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8; font-size: 0.875rem;">
                @foreach($plan['key_features'] as $feature)
                <li style="margin-bottom: 0.25rem;">{{ $feature }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endforeach
</div>

<!-- Pro Tip -->
<div style="background-color: #eff6ff; border: 2px solid #3b82f6; padding: 1.25rem; margin: 2rem 0; border-radius: 0.5rem;">
    <p style="margin: 0; color: #1e40af; font-size: 0.875rem;">
        <strong>💡 Pro Tip:</strong> Start with our free trial to explore all features before committing to a paid plan!
    </p>
</div>

<!-- Need Help Section -->
<div style="margin-top: 2rem;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        📞 Need Help?
    </h3>
    <p style="margin: 0 0 0.75rem 0; color: #4b5563;">
        Our support team is here to assist you with:
    </p>
    <ul style="margin: 0; padding-left: 1.5rem; color: #4b5563; line-height: 1.8;">
        <li style="margin-bottom: 0.25rem;">Setting up your business profile</li>
        <li style="margin-bottom: 0.25rem;">Choosing the right subscription plan</li>
        <li style="margin-bottom: 0.25rem;">Adding your first products</li>
        <li style="margin-bottom: 0.25rem;">Any questions you may have</li>
    </ul>
</div>

<!-- Closing -->
<hr style="border: 0; border-top: 1px solid #e5e7eb; margin: 2rem 0;">

<p style="margin: 0 0 0.5rem 0; color: #4b5563;">
    Thank you for choosing Poachy. We're excited to help your business grow!
</p>

<p style="margin: 0; color: #4b5563;">
    <strong>Best regards,</strong><br>
    The {{ config('app.name') }} Team
</p>

<!-- Next Steps Summary -->
<div style="background-color: #f9fafb; padding: 1rem; margin-top: 2rem; border-radius: 0.375rem; border: 1px solid #e5e7eb;">
    <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.75rem; font-weight: 600; text-transform: uppercase;">
        Quick Checklist:
    </p>
    <ol style="margin: 0; padding-left: 1.25rem; color: #6b7280; font-size: 0.875rem; line-height: 1.6;">
        <li>Log in to your dashboard</li>
        <li>Complete your business profile</li>
        <li>Subscribe to a plan</li>
        <li>Start adding products</li>
    </ol>
</div>
@endsection