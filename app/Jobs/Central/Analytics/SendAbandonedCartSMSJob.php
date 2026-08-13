<?php

namespace App\Jobs\Central\Analytics;

use App\Mail\Central\Marketplace\CartRecoveryMail;
use App\Models\ShoppingCart;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
            ->with(['customer.user', 'items.marketplaceProduct'])
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
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer->id,
                'accepts_sms' => $cart->customer->accepts_sms,
                'phone_verified' => $cart->customer->phone_verified,
                'is_active' => $cart->customer->is_active,
            ]);

            return;
        }

        // Check if already sent
        if ($cart->recovery_sms_sent) {
            Log::info('Cart recovery SMS already sent', ['cart_id' => $cart->id]);

            return;
        }

        if ($cart->recovery_email_sent) {
            Log::info('Skipping cart recovery SMS email fallback - recovery email already sent', [
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer->id,
            ]);

            return;
        }

        try {
            // SMS provider integration is deferred. Until then, the SMS recovery
            // path uses the existing cart recovery email template and Mailpit in
            // local/test environments.
            Mail::to($cart->customer->user->email)->send(new CartRecoveryMail(
                cart: $cart,
                customerName: $cart->customer->user->name,
                cartUrl: config('app.frontend_url').'/cart',
            ));

            Log::info('Cart recovery SMS fallback email sent', [
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer->id,
                'phone' => $cart->customer->phone,
                'email' => $cart->customer->user->email,
            ]);

            $cart->update([
                'recovery_email_sent' => true,
                'recovery_email_sent_at' => now(),
                'recovery_sms_sent' => true,
                'recovery_sms_sent_at' => now(),
            ]);

            Log::info('Cart recovery SMS sent successfully', [
                'cart_id' => $cart->id,
                'customer_id' => $cart->customer->id,
                'phone' => $cart->customer->phone,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send cart recovery SMS', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
