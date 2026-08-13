<?php

namespace App\Services\Tenant\Marketplace;

use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\MarketplaceSale;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class MarketplaceOrderStaffNotificationService
{
    /**
     * @param  array<string, mixed>  $orderPayload
     * @param  array<int, int>  $storeIds
     */
    public function notifyNewOrderReserved(array $orderPayload, array $storeIds): void
    {
        $this->notifyRecipients(
            recipients: $this->resolveRecipients($storeIds),
            subject: 'New marketplace order reserved',
            body: $this->buildNewOrderBody($orderPayload),
            metadata: [
                'notification_type' => 'marketplace_order_reserved',
                'central_order_id' => $orderPayload['order_id'] ?? null,
                'order_number' => $orderPayload['order_number'] ?? null,
                'store_ids' => $storeIds,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $paymentPayload
     */
    public function notifyPaymentConfirmed(array $paymentPayload, MarketplaceSale $sale): void
    {
        $this->notifyRecipients(
            recipients: $this->resolveRecipients([$sale->store_id]),
            subject: 'Marketplace payment confirmed',
            body: $this->buildPaymentConfirmedBody($paymentPayload, $sale),
            metadata: [
                'notification_type' => 'marketplace_payment_confirmed',
                'central_order_id' => $paymentPayload['order_id'] ?? $sale->central_order_id,
                'order_number' => $paymentPayload['order_number'] ?? $sale->sale_number,
                'marketplace_sale_id' => $sale->id,
                'store_id' => $sale->store_id,
            ],
        );
    }

    /**
     * @param  array<int, int|null>  $storeIds
     * @return Collection<int, User>
     */
    private function resolveRecipients(array $storeIds): Collection
    {
        $users = collect();
        $storeIds = collect($storeIds)->filter()->unique()->values();

        if ($storeIds->isNotEmpty()) {
            $managerIds = Store::whereIn('id', $storeIds)
                ->whereNotNull('manager_id')
                ->pluck('manager_id');

            if ($managerIds->isNotEmpty()) {
                $users = $users->merge(User::whereIn('id', $managerIds)->get());
            }
        }

        $users = $users->merge(User::role('owner')->get());

        return $users
            ->filter(fn (User $user) => $user->is_active && ! empty($user->email))
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  array<string, mixed>  $metadata
     */
    private function notifyRecipients(Collection $recipients, string $subject, string $body, array $metadata): void
    {
        if ($recipients->isEmpty()) {
            Log::warning('No tenant users available for marketplace staff notification', [
                'metadata' => $metadata,
            ]);

            return;
        }

        foreach ($recipients as $recipient) {
            SendNotificationJob::dispatch(
                channel: 'email',
                recipient: $recipient->email,
                message: [
                    'subject' => $subject,
                    'body' => $body,
                ],
                metadata: array_merge($metadata, [
                    'recipient_user_id' => $recipient->id,
                ]),
            )->onQueue('sync-normal');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildNewOrderBody(array $payload): string
    {
        $orderNumber = $payload['order_number'] ?? ('#'.($payload['order_id'] ?? 'unknown'));
        $amount = $this->formatMoney($payload['amount'] ?? $payload['total_amount'] ?? null);
        $itemSummary = $this->summarizeItems($payload['items'] ?? []);

        return trim("Marketplace order {$orderNumber} has been reserved in tenant inventory.\n\n".
            "Amount: {$amount}\n".
            "Items: {$itemSummary}\n\n".
            'Watch for payment confirmation before fulfillment.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildPaymentConfirmedBody(array $payload, MarketplaceSale $sale): string
    {
        $orderNumber = $payload['order_number'] ?? $sale->sale_number;
        $amount = $this->formatMoney($payload['amount'] ?? $sale->total_amount);
        $fulfillmentType = $payload['fulfillment_type'] ?? $sale->fulfillment_type ?? 'delivery';
        $itemSummary = $this->summarizeItems($payload['items'] ?? []);

        return trim("Payment has been confirmed for marketplace order {$orderNumber}.\n\n".
            "Amount: {$amount}\n".
            "Fulfillment: {$fulfillmentType}\n".
            "Items: {$itemSummary}\n\n".
            'This order is ready for store fulfillment.');
    }

    private function summarizeItems(mixed $items): string
    {
        if (! is_array($items) || $items === []) {
            return 'No item details provided';
        }

        return collect($items)
            ->map(function (array $item): string {
                $name = $item['product_name'] ?? $item['name'] ?? 'Item';
                $quantity = $item['quantity'] ?? 1;

                return "{$name} x {$quantity}";
            })
            ->implode(', ');
    }

    private function formatMoney(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'Not provided';
        }

        return 'KES '.number_format((float) $amount, 2);
    }
}
