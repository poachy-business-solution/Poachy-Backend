<?php

namespace App\Jobs\Central\Analytics;

use App\Mail\Central\Marketplace\CartRecoveryMail;
use App\Models\ShoppingCart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendAbandonedCartEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $cartId,
    ) {
        $this->onQueue('sync-low');
    }

    public function handle(): void
    {
        $cart = ShoppingCart::on('central')
            ->with(['customer', 'items.marketplaceProduct'])
            ->find($this->cartId);

        if (! $cart) {
            Log::warning('Abandoned cart not found for recovery email', ['cart_id' => $this->cartId]);

            return;
        }

        // CRITICAL: Check marketing consent
        if (! $cart->customer) {
            Log::info('Skipping cart recovery email - no customer associated', ['cart_id' => $cart->id]);

            return;
        }

        if (! $cart->customer->accepts_marketing || ! $cart->customer->is_active) {
            Log::info('Skipping cart recovery email - customer does not accept marketing or is inactive', [
                'cart_id'          => $cart->id,
                'customer_id'      => $cart->customer->id,
                'accepts_marketing' => $cart->customer->accepts_marketing,
                'is_active'        => $cart->customer->is_active,
            ]);

            return;
        }

        // Check if already sent
        if ($cart->recovery_email_sent) {
            Log::info('Cart recovery email already sent', ['cart_id' => $cart->id]);

            return;
        }

        try {
            // Send cart recovery email
            Mail::to($cart->customer->email)->send(new CartRecoveryMail(
                cart: $cart,
                customerName: $cart->customer->name,
                cartUrl: config('app.frontend_url') . '/cart',
            ));

            // Mark as sent
            $cart->update([
                'recovery_email_sent'    => true,
                'recovery_email_sent_at' => now(),
            ]);

            Log::info('Cart recovery email sent successfully', [
                'cart_id'     => $cart->id,
                'customer_id' => $cart->customer->id,
                'email'       => $cart->customer->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send cart recovery email', [
                'cart_id' => $cart->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
