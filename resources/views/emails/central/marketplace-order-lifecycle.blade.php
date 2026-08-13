@extends('emails.layouts.app')

@section('title', $headline)

@section('content')
<div style="text-align: center; margin-bottom: 1.5rem;">
    <h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin: 0;">
        {{ $headline }}
    </h2>
    <p style="margin: 0.75rem 0 0; color: #4b5563; font-size: 1rem;">
        Hi {{ $customerName }}, {{ $intro }}
    </p>
</div>

<div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Order {{ $order->order_number }}
    </h3>

    <p style="margin: 0 0 0.5rem; color: #4b5563;">
        <strong>Merchant:</strong> {{ $order->merchant_name }}
    </p>
    <p style="margin: 0 0 0.5rem; color: #4b5563;">
        <strong>Status:</strong> {{ $order->order_status->label() }}
    </p>
    <p style="margin: 0 0 0.5rem; color: #4b5563;">
        <strong>Fulfillment:</strong> {{ $order->fulfillment_type->label() }}
    </p>

    @if($payment)
        <p style="margin: 0 0 0.5rem; color: #4b5563;">
            <strong>Payment:</strong> {{ $payment->payment_status->label() }}
        </p>
    @endif

    @if(! empty($context['reason']))
        <p style="margin: 0.75rem 0 0; color: #4b5563;">
            <strong>Reason:</strong> {{ $context['reason'] }}
        </p>
    @endif
</div>

@if($order->items->isNotEmpty())
<div style="background-color: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1.5rem; margin: 1.5rem 0;">
    <h3 style="color: #111827; font-size: 1.125rem; font-weight: 600; margin-top: 0; margin-bottom: 1rem;">
        Items
    </h3>

    @foreach($order->items as $item)
    <div style="padding: 0.75rem 0; border-bottom: 1px solid #e5e7eb;">
        <p style="margin: 0; font-weight: 600; color: #111827;">{{ $item->product_name }}</p>
        <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: #6b7280;">
            Quantity: {{ number_format((float) $item->quantity, 2) }}
            @if($item->variant_name)
                - {{ $item->variant_name }}
            @endif
        </p>
        <p style="margin: 0.25rem 0 0; font-weight: 600; color: #2563eb;">
            KES {{ number_format((float) $item->subtotal, 2) }}
        </p>
    </div>
    @endforeach

    <div style="padding-top: 1rem; margin-top: 0.5rem;">
        <p style="margin: 0 0 0.5rem; color: #4b5563;">Subtotal: KES {{ number_format((float) $order->subtotal, 2) }}</p>
        @if((float) $order->delivery_fee > 0)
            <p style="margin: 0 0 0.5rem; color: #4b5563;">Delivery: KES {{ number_format((float) $order->delivery_fee, 2) }}</p>
        @endif
        <p style="margin: 0; font-weight: 700; color: #111827; font-size: 1.125rem;">
            Total: KES {{ number_format((float) $order->total_amount, 2) }}
        </p>
    </div>
</div>
@endif

@if($orderUrl)
<div style="text-align: center; margin: 2rem 0;">
    <a href="{{ $orderUrl }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; padding: 0.875rem 2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 1rem;">
        View Order
    </a>
</div>
@endif

<p style="margin-top: 2rem; color: #6b7280; font-size: 0.875rem; text-align: center;">
    Questions? Contact us at <a href="mailto:support@poachy.com" style="color: #2563eb; text-decoration: none;">support@poachy.com</a>
</p>
@endsection
