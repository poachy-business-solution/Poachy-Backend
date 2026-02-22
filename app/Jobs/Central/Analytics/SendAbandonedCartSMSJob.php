<?php

namespace App\Jobs\Central\Analytics;

use App\Models\ShoppingCart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAbandonedCartSMSJob implements ShouldQueue
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
            Log::warning('Abandoned cart not found for recovery SMS', ['cart_id' => $this->cartId]);

            return;
        }

        // CRITICAL: Check SMS consent and phone verification
        if (! $cart->customer) {
            Log::info('Skipping cart recovery SMS - no customer associated', ['cart_id' => $cart->id]);

            return;
        }

        if (! $cart->customer->accepts_sms || ! $cart->customer->phone_verified || ! $cart->customer->is_active) {
            Log::info('Skipping cart recovery SMS - customer does not accept SMS, phone not verified, or inactive', [
                'cart_id'        => $cart->id,
                'customer_id'    => $cart->customer->id,
                'accepts_sms'    => $cart->customer->accepts_sms,
                'phone_verified' => $cart->customer->phone_verified,
                'is_active'      => $cart->customer->is_active,
            ]);

            return;
        }

        // Check if already sent
        if ($cart->recovery_sms_sent) {
            Log::info('Cart recovery SMS already sent', ['cart_id' => $cart->id]);

            return;
        }

        try {
            // TODO: Integrate with SMS service (Twilio, Africa's Talking, etc.)
            // Example SMS content:
            $smsContent = sprintf(
                'Hi %s! You left %d %s in your Poachy cart (Total: KES %s). Complete your purchase now: %s',
                $cart->customer->name,
                $cart->getItemCount(),
                $cart->getItemCount() === 1 ? 'item' : 'items',
                number_format($cart->getSubtotal(), 2),
                config('app.frontend_url') . '/cart'
            );

            // Placeholder: Log SMS content (replace with actual SMS service call)
            Log::info('Cart recovery SMS would be sent', [
                'cart_id'     => $cart->id,
                'customer_id' => $cart->customer->id,
                'phone'       => $cart->customer->phone,
                'message'     => $smsContent,
            ]);

            // Mark as sent
            $cart->update([
                'recovery_sms_sent'    => true,
                'recovery_sms_sent_at' => now(),
            ]);

            Log::info('Cart recovery SMS sent successfully', [
                'cart_id'     => $cart->id,
                'customer_id' => $cart->customer->id,
                'phone'       => $cart->customer->phone,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send cart recovery SMS', [
                'cart_id' => $cart->id,
                'error'   => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
