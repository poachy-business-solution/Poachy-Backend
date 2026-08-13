<?php

namespace App\Mail\Central\Marketplace;

use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MarketplaceOrderLifecycleMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly MarketplaceOrder $order,
        public readonly string $customerName,
        public readonly string $lifecycleType,
        public readonly ?MarketplaceOrderPayment $payment = null,
        public readonly array $context = [],
        public readonly ?string $orderUrl = null,
    ) {
        $this->onQueue('sync-normal');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectFor($this->lifecycleType),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.marketplace-order-lifecycle',
            with: [
                'order' => $this->order,
                'customerName' => $this->customerName,
                'lifecycleType' => $this->lifecycleType,
                'payment' => $this->payment,
                'context' => $this->context,
                'orderUrl' => $this->orderUrl,
                'headline' => $this->headlineFor($this->lifecycleType),
                'intro' => $this->introFor($this->lifecycleType),
            ],
        );
    }

    private function subjectFor(string $type): string
    {
        return match ($type) {
            'order_placed' => "We've received your Poachy order {$this->order->order_number}",
            'reservation_confirmed' => "Your items are reserved for order {$this->order->order_number}",
            'reservation_failed' => "We couldn't reserve order {$this->order->order_number}",
            'reservation_expired' => "Order {$this->order->order_number} was cancelled",
            'payment_confirmed' => "Payment received for order {$this->order->order_number}",
            'payment_failed' => "Payment failed for order {$this->order->order_number}",
            'payment_timeout' => "Payment window expired for order {$this->order->order_number}",
            'order_cancelled' => "Order {$this->order->order_number} was cancelled",
            'order_refunded' => "Order {$this->order->order_number} was refunded",
            'order_completed' => "Order {$this->order->order_number} is complete",
            'order_ready_for_pickup' => "Order {$this->order->order_number} is ready for pickup",
            'order_out_for_delivery' => "Order {$this->order->order_number} is out for delivery",
            'order_processing' => "Order {$this->order->order_number} is being prepared",
            default => "Update for Poachy order {$this->order->order_number}",
        };
    }

    private function headlineFor(string $type): string
    {
        return match ($type) {
            'order_placed' => 'Order Received',
            'reservation_confirmed' => 'Items Reserved',
            'reservation_failed' => 'Reservation Failed',
            'reservation_expired' => 'Reservation Expired',
            'payment_confirmed' => 'Payment Confirmed',
            'payment_failed' => 'Payment Failed',
            'payment_timeout' => 'Payment Window Expired',
            'order_cancelled' => 'Order Cancelled',
            'order_refunded' => 'Order Refunded',
            'order_completed' => 'Order Complete',
            'order_ready_for_pickup' => 'Ready for Pickup',
            'order_out_for_delivery' => 'Out for Delivery',
            'order_processing' => 'Order Processing',
            default => 'Order Update',
        };
    }

    private function introFor(string $type): string
    {
        return match ($type) {
            'order_placed' => 'Your order has been created. We are checking availability with the merchant.',
            'reservation_confirmed' => 'The merchant has reserved your items. You can now complete payment before the payment window closes.',
            'reservation_failed' => 'The merchant could not reserve one or more items in your order.',
            'reservation_expired' => 'The merchant did not confirm availability before the reservation window closed, so the order was cancelled.',
            'payment_confirmed' => 'We have received your payment and sent the order to the merchant for fulfillment.',
            'payment_failed' => 'Your payment did not go through. You can retry while the order is still eligible for payment.',
            'payment_timeout' => 'The payment deadline passed, so the order reservation was released.',
            'order_cancelled' => 'This order has been cancelled.',
            'order_refunded' => 'This order has been marked as refunded.',
            'order_completed' => 'Your order has been completed. Thank you for shopping with Poachy.',
            'order_ready_for_pickup' => 'Your order is ready for pickup from the merchant.',
            'order_out_for_delivery' => 'Your order is on its way.',
            'order_processing' => 'The merchant is preparing your order.',
            default => 'There is an update on your order.',
        };
    }
}
