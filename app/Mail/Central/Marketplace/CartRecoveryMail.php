<?php

namespace App\Mail\Central\Marketplace;

use App\Models\ShoppingCart;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CartRecoveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ShoppingCart $cart,
        public readonly string $customerName,
        public readonly string $cartUrl,
    ) {
        $this->onQueue('sync-normal');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Poachy Cart is Waiting for You!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.central.cart-recovery',
            with: [
                'cart'         => $this->cart,
                'customerName' => $this->customerName,
                'cartUrl'      => $this->cartUrl,
                'itemCount'    => $this->cart->getItemCount(),
                'subtotal'     => $this->cart->getSubtotal(),
            ],
        );
    }
}
