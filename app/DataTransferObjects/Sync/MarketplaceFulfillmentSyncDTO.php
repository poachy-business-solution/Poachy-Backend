<?php

namespace App\DataTransferObjects\Sync;

use App\Models\Tenant\MarketplaceSale;

class MarketplaceFulfillmentSyncDTO
{
    public function __construct(
        public readonly string $tenantId,
        public readonly int $saleId,
        public readonly int $centralOrderId,
        public readonly string $fulfillmentStatus,
        public readonly ?string $notes,
        public readonly ?string $courierCompany,
        public readonly ?string $courierName,
        public readonly ?string $courierPhone,
        public readonly ?string $trackingNumber,
        public readonly ?string $trackingUrl,
        public readonly ?string $deliveryProofType,
        public readonly ?string $deliveryProofData,
        public readonly ?string $receivedByName,
        public readonly ?string $receivedByPhone,
    ) {}

    /**
     * Create DTO from tenant MarketplaceSale model plus the delivery data
     * submitted alongside this specific status transition.
     */
    public static function fromModel(MarketplaceSale $sale, array $deliveryData = [], ?string $notes = null): self
    {
        if (! tenant()) {
            throw new \RuntimeException('Cannot create MarketplaceFulfillmentSyncDTO outside tenant context');
        }

        if (! $sale->id) {
            throw new \InvalidArgumentException('MarketplaceSale must be persisted before syncing');
        }

        if (! $sale->central_order_id) {
            throw new \InvalidArgumentException('MarketplaceSale must have a central_order_id to sync');
        }

        return new self(
            tenantId: tenant()->id,
            saleId: $sale->id,
            centralOrderId: $sale->central_order_id,
            fulfillmentStatus: $sale->fulfillment_status->value,
            notes: $notes,
            courierCompany: $deliveryData['courier_company'] ?? null,
            courierName: $deliveryData['courier_name'] ?? null,
            courierPhone: $deliveryData['courier_phone'] ?? null,
            trackingNumber: $deliveryData['tracking_number'] ?? null,
            trackingUrl: $deliveryData['tracking_url'] ?? null,
            deliveryProofType: $deliveryData['delivery_proof_type'] ?? null,
            deliveryProofData: $deliveryData['delivery_proof_data'] ?? null,
            receivedByName: $deliveryData['received_by_name'] ?? null,
            receivedByPhone: $deliveryData['received_by_phone'] ?? null,
        );
    }

    /**
     * Create DTO from array (for queue deserialization on central side).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: $data['tenant_id'],
            saleId: $data['sale_id'],
            centralOrderId: $data['central_order_id'],
            fulfillmentStatus: $data['fulfillment_status'],
            notes: $data['notes'] ?? null,
            courierCompany: $data['courier_company'] ?? null,
            courierName: $data['courier_name'] ?? null,
            courierPhone: $data['courier_phone'] ?? null,
            trackingNumber: $data['tracking_number'] ?? null,
            trackingUrl: $data['tracking_url'] ?? null,
            deliveryProofType: $data['delivery_proof_type'] ?? null,
            deliveryProofData: $data['delivery_proof_data'] ?? null,
            receivedByName: $data['received_by_name'] ?? null,
            receivedByPhone: $data['received_by_phone'] ?? null,
        );
    }

    /**
     * Serialize DTO to array for queue payload storage.
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'sale_id' => $this->saleId,
            'central_order_id' => $this->centralOrderId,
            'fulfillment_status' => $this->fulfillmentStatus,
            'notes' => $this->notes,
            'courier_company' => $this->courierCompany,
            'courier_name' => $this->courierName,
            'courier_phone' => $this->courierPhone,
            'tracking_number' => $this->trackingNumber,
            'tracking_url' => $this->trackingUrl,
            'delivery_proof_type' => $this->deliveryProofType,
            'delivery_proof_data' => $this->deliveryProofData,
            'received_by_name' => $this->receivedByName,
            'received_by_phone' => $this->receivedByPhone,
        ];
    }

    /**
     * Whether any delivery-tracking data was submitted alongside this status
     * update (courier info, tracking, or proof-of-delivery fields).
     */
    public function hasDeliveryData(): bool
    {
        return $this->courierCompany !== null
            || $this->courierName !== null
            || $this->courierPhone !== null
            || $this->trackingNumber !== null
            || $this->trackingUrl !== null
            || $this->deliveryProofType !== null
            || $this->deliveryProofData !== null
            || $this->receivedByName !== null
            || $this->receivedByPhone !== null;
    }

    /**
     * Generate idempotency key for deduplication.
     */
    public function generateIdempotencyKey(string $action = 'update'): string
    {
        $payload = json_encode($this->toArray());
        $payloadHash = hash('sha256', $payload);

        return md5(
            $this->tenantId.
                'marketplace_fulfillment'.
                $this->saleId.
                $action.
                $payloadHash
        );
    }
}
