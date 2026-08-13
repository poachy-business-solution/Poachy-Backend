<?php

namespace App\Services\Tenant\Supplier;

use App\Jobs\Tenant\SendNotificationJob;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\SupplierPayment;
use Illuminate\Support\Facades\Log;

class SupplierNotificationService
{
    public function notifyPurchaseOrderSent(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing([
            'supplier',
            'store',
            'items.product',
            'items.productVariant',
            'items.uom',
        ]);

        if (! $this->hasSupplierEmail($purchaseOrder->supplier, 'purchase_order_sent', [
            'purchase_order_id' => $purchaseOrder->id,
            'po_number' => $purchaseOrder->po_number,
        ])) {
            return;
        }

        $this->dispatchEmail(
            recipient: $purchaseOrder->supplier->email,
            subject: "Purchase order {$purchaseOrder->po_number}",
            body: $this->buildPurchaseOrderSentBody($purchaseOrder),
            metadata: [
                'notification_type' => 'purchase_order_sent',
                'supplier_id' => $purchaseOrder->supplier_id,
                'purchase_order_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'store_id' => $purchaseOrder->store_id,
            ],
        );
    }

    public function notifySupplierPaymentRecorded(SupplierPayment $payment): void
    {
        $payment->loadMissing([
            'supplier',
            'purchaseOrder',
            'createdBy',
        ]);

        if (! $this->hasSupplierEmail($payment->supplier, 'supplier_payment_recorded', [
            'supplier_payment_id' => $payment->id,
            'payment_number' => $payment->payment_number,
        ])) {
            return;
        }

        $this->dispatchEmail(
            recipient: $payment->supplier->email,
            subject: "Payment recorded: {$payment->payment_number}",
            body: $this->buildSupplierPaymentRecordedBody($payment),
            metadata: [
                'notification_type' => 'supplier_payment_recorded',
                'supplier_id' => $payment->supplier_id,
                'supplier_payment_id' => $payment->id,
                'payment_number' => $payment->payment_number,
                'purchase_order_id' => $payment->purchase_order_id,
                'po_number' => $payment->purchaseOrder?->po_number,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function dispatchEmail(string $recipient, string $subject, string $body, array $metadata): void
    {
        SendNotificationJob::dispatch(
            channel: 'email',
            recipient: $recipient,
            message: [
                'subject' => $subject,
                'body' => $body,
            ],
            metadata: $metadata,
        )->onQueue('sync-normal');
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function hasSupplierEmail(?Supplier $supplier, string $notificationType, array $context): bool
    {
        if ($supplier && filled($supplier->email)) {
            return true;
        }

        Log::warning('Supplier email notification skipped because supplier email is missing', array_merge($context, [
            'notification_type' => $notificationType,
            'supplier_id' => $supplier?->id,
            'tenant_id' => tenant()->id ?? 'system',
        ]));

        return false;
    }

    private function buildPurchaseOrderSentBody(PurchaseOrder $purchaseOrder): string
    {
        $supplierName = $purchaseOrder->supplier?->name ?? 'Supplier';
        $storeName = $purchaseOrder->store?->name ?? 'the store';
        $orderDate = $purchaseOrder->order_date?->format('Y-m-d') ?? 'Not provided';
        $expectedDeliveryDate = $purchaseOrder->expected_delivery_date?->format('Y-m-d') ?? 'Not provided';
        $totalAmount = $this->formatMoney($purchaseOrder->total_amount);
        $items = $this->summarizePurchaseOrderItems($purchaseOrder);

        return trim("Hello {$supplierName},\n\n".
            "Purchase order {$purchaseOrder->po_number} has been sent by {$storeName}.\n\n".
            "Order date: {$orderDate}\n".
            "Expected delivery date: {$expectedDeliveryDate}\n".
            "Total: {$totalAmount}\n".
            "Items: {$items}\n\n".
            'Please use this purchase order number in your delivery and invoice references.');
    }

    private function buildSupplierPaymentRecordedBody(SupplierPayment $payment): string
    {
        $supplierName = $payment->supplier?->name ?? 'Supplier';
        $purchaseOrderNumber = $payment->purchaseOrder?->po_number ?? 'General supplier payment';
        $paymentDate = $payment->payment_date?->format('Y-m-d') ?? 'Not provided';
        $method = $payment->payment_method?->label() ?? 'Not provided';
        $reference = $payment->reference_number ?: 'Not provided';
        $amount = $this->formatMoney($payment->amount);

        return trim("Hello {$supplierName},\n\n".
            "Payment {$payment->payment_number} has been recorded.\n\n".
            "Amount: {$amount}\n".
            "Payment date: {$paymentDate}\n".
            "Method: {$method}\n".
            "Reference: {$reference}\n".
            "Purchase order: {$purchaseOrderNumber}\n\n".
            'This confirms the payment entry in our supplier account records.');
    }

    private function summarizePurchaseOrderItems(PurchaseOrder $purchaseOrder): string
    {
        if ($purchaseOrder->items->isEmpty()) {
            return 'No item details provided';
        }

        return $purchaseOrder->items
            ->map(function ($item): string {
                $productName = $item->product?->name ?? 'Item';
                $variantName = $item->productVariant?->name;
                $uom = $item->uom?->code ?? $item->uom?->name;
                $quantity = rtrim(rtrim(number_format((float) $item->quantity_ordered, 4, '.', ''), '0'), '.');
                $line = "{$productName}".($variantName ? " ({$variantName})" : '')." x {$quantity}";

                return $uom ? "{$line} {$uom}" : $line;
            })
            ->implode(', ');
    }

    private function formatMoney(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'KES 0.00';
        }

        return 'KES '.number_format((float) $amount, 2);
    }
}
