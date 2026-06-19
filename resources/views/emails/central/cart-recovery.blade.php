@extends('emails.layouts.app')

@section('title', 'Your Cart is Waiting')

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <div style="font-size: 3rem; margin-bottom: 0.5rem;">🛒</div>
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        Hey {{ $customerName }}, You Left Something Behind!
    </h2>
</div>

<p style="margin-bottom: 1rem; color: #4b5563; text-align: center; font-size: 1.125rem;">
    We noticed you left {{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }} in your cart. Don't worry, we've saved {{ $itemCount === 1 ? 'it' : 'them' }} for you!
</p>

<!-- Cart Summary -->
<div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Your Cart Summary
    </h3>

    @foreach($cart->items as $item)
    <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
        <div>
            <p style="margin: 0; font-weight: 600; color: #111827;">{{ $item->product_name }}</p>
            <p style="margin: 0; font-size: 0.875rem; color: #6b7280;">Quantity: {{ $item->quantity }}</p>
        </div>
        <p style="margin: 0; font-weight: 600; color: #2563eb;">KES {{ number_format($item->getLineTotal(), 2) }}</p>
    </div>
    @endforeach

    <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; margin-top: 0.5rem;">
        <p style="margin: 0; font-weight: 700; color: #111827; font-size: 1.125rem;">Subtotal:</p>
        <p style="margin: 0; font-weight: 700; color: #2563eb; font-size: 1.25rem;">KES {{ number_format($subtotal, 2) }}</p>
    </div>
</div>

<!-- Call to Action Button -->
<div style="text-align: center; margin: 2rem 0;">
    <a href="{{ $cartUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 0.875rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 1rem;">
        Complete Your Purchase
    </a>
</div>

<!-- Why Shop With Us Section -->
<div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 1.5rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <h3 style="color: #1e40af; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        ✨ Why Shop With Poachy?
    </h3>
    <ul style="margin: 0; padding-left: 1.5rem; color: #1e3a8a; line-height: 1.8;">
        <li style="margin-bottom: 0.5rem;"><strong>Fast Delivery</strong> - Get your items delivered quickly</li>
        <li style="margin-bottom: 0.5rem;"><strong>Secure Payment</strong> - Your payment information is safe with us</li>
        <li style="margin-bottom: 0.5rem;"><strong>Quality Products</strong> - Shop from trusted local merchants</li>
        <li style="margin-bottom: 0.5rem;"><strong>Great Prices</strong> - Competitive pricing on all products</li>
    </ul>
</div>

<p style="margin-top: 2rem; color: #6b7280; font-size: 0.875rem; text-align: center;">
    This is a friendly reminder about your abandoned cart. Your cart items are reserved for a limited time.
</p>

<p style="margin-top: 1rem; color: #6b7280; font-size: 0.875rem; text-align: center;">
    Questions? Contact us at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection
