<?php

namespace App\Services\Central\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use App\Enums\Central\OutboundSyncAction;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceReservation;
use App\Services\Shared\Messaging\Messenger;
use App\Support\OrderRef;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Reserve & collect. A buyer holds one item at one shop for 24 h; the shop's own inventory
 * reservation does the holding (synced like an order); the cashier redeems the code on the POS.
 */
class ReservationService
{
    /** Open holds one buyer may have at a time. */
    public const MAX_OPEN_PER_BUYER = 5;

    public const MAX_QUANTITY = 10;

    public function __construct(
        private readonly OutboundSyncService $sync,
        private readonly Messenger $messenger,
    ) {}

    /**
     * Create a hold (idempotent per buyer + Idempotency-Key) and queue the stock hold to the shop.
     *
     * @param  array{product_slug?: string, product_id?: int, quantity: int|float, ref?: string|null}  $data
     */
    public function create(MarketplaceCustomer $customer, array $data, string $idempotencyKey): MarketplaceReservation
    {
        $existing = MarketplaceReservation::on('central')
            ->where('customer_id', $customer->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        $product = $this->listedProduct($data);
        $quantity = (float) $data['quantity'];

        if ($product->stock_status === 'out_of_stock' || (float) $product->available_quantity < $quantity) {
            throw ValidationException::withMessages(['quantity' => ['The shop does not have that many in stock right now.']]);
        }

        $open = MarketplaceReservation::on('central')->open()->where('customer_id', $customer->id);

        if ((clone $open)->count() >= self::MAX_OPEN_PER_BUYER) {
            throw ValidationException::withMessages(['product' => ['You already have '.self::MAX_OPEN_PER_BUYER.' items on hold. Collect or cancel one first.']]);
        }

        if ((clone $open)->where('marketplace_product_id', $product->id)->exists()) {
            throw ValidationException::withMessages(['product' => ['You already have this item on hold at this shop.']]);
        }

        $customer->loadMissing('user');

        $reservation = DB::connection('central')->transaction(fn () => MarketplaceReservation::on('central')->create([
            'code' => MarketplaceReservation::generateCode(),
            'tenant_id' => $product->tenant_id,
            'customer_id' => $customer->id,
            'marketplace_product_id' => $product->id,
            'tenant_product_id' => $product->tenant_product_id,
            'tenant_variant_id' => $product->tenant_variant_id,
            'tenant_bundle_id' => $product->tenant_bundle_id,
            'product_name' => $product->name,
            'product_slug' => $product->slug,
            'product_image' => $product->primary_image,
            'quantity' => $quantity,
            'unit_price' => $product->online_price,
            'uom_code' => $product->base_uom_code,
            'status' => MarketplaceReservationStatus::Pending,
            'ref' => OrderRef::for($data['ref'] ?? null, $data['ref_shop'] ?? null, (string) $product->tenant_id),
            'buyer_name' => $customer->user?->name,
            'buyer_phone' => $customer->phone,
            'expires_at' => now()->addHours(MarketplaceReservation::HOLD_HOURS),
            'idempotency_key' => $idempotencyKey,
        ]));

        $this->sync->queueReservationSync($reservation, OutboundSyncAction::ReservationHold);

        Log::info('Reservation created', ['reservation_id' => $reservation->id, 'code' => $reservation->code, 'tenant_id' => $reservation->tenant_id]);

        return $reservation;
    }

    /** The shop confirmed the stock hold: tell the buyer the code is live. */
    public function markHeld(MarketplaceReservation $reservation): MarketplaceReservation
    {
        if ($reservation->status !== MarketplaceReservationStatus::Pending) {
            return $reservation;
        }

        $reservation->forceFill(['status' => MarketplaceReservationStatus::Active, 'held_at' => now()])->save();
        $this->smsBuyer($reservation, sprintf(
            'Poachy: %s is held for you at %s until %s. Show code %s at the counter and pay there.',
            $this->itemLine($reservation),
            $this->shopName($reservation),
            $reservation->expires_at->timezone(config('app.timezone'))->format('D j M, g:i a'),
            $reservation->code,
        ), 'reservation_held');

        return $reservation;
    }

    /** The shop could not hold the stock. */
    public function markFailed(MarketplaceReservation $reservation, ?string $reason): MarketplaceReservation
    {
        if ($reservation->status !== MarketplaceReservationStatus::Pending) {
            return $reservation;
        }

        $reservation->forceFill(['status' => MarketplaceReservationStatus::Failed, 'failed_reason' => $reason ? mb_substr($reason, 0, 500) : null])->save();
        $this->smsBuyer($reservation, sprintf(
            'Poachy: sorry, %s could not hold %s right now. Nothing to pay. Try another shop on poachy.com.',
            $this->shopName($reservation),
            $this->itemLine($reservation),
        ), 'reservation_failed');

        return $reservation;
    }

    /** The buyer cancels; the shop releases its hold. */
    public function cancel(MarketplaceReservation $reservation): MarketplaceReservation
    {
        if (! $reservation->status->isOpen()) {
            throw ValidationException::withMessages(['status' => ['This reservation is already '.$reservation->status->label().'.']]);
        }

        $reservation->forceFill(['status' => MarketplaceReservationStatus::Cancelled, 'cancelled_at' => now()])->save();
        $this->sync->queueReservationSync($reservation, OutboundSyncAction::ReservationRelease, 'Buyer cancelled');

        return $reservation;
    }

    /**
     * Expire open holds past their time and release the shops' stock. Returns how many.
     */
    public function expireDue(): int
    {
        $due = MarketplaceReservation::on('central')->open()->where('expires_at', '<', now())->get();

        foreach ($due as $reservation) {
            $reservation->forceFill(['status' => MarketplaceReservationStatus::Expired])->save();
            $this->sync->queueReservationSync($reservation, OutboundSyncAction::ReservationRelease, 'Reservation expired');
        }

        return $due->count();
    }

    /**
     * The cashier took payment for the code: link the sale. The caller (tenant context) releases
     * the inventory hold since the sale itself has deducted the stock.
     */
    public function redeem(MarketplaceReservation $reservation, int $saleId, string $saleNumber, ?int $redeemedBy): MarketplaceReservation
    {
        $reservation->forceFill([
            'status' => MarketplaceReservationStatus::Redeemed,
            'redeemed_at' => now(),
            'sale_id' => $saleId,
            'sale_number' => $saleNumber,
            'redeemed_by' => $redeemedBy,
        ])->save();

        Log::info('Reservation redeemed', ['reservation_id' => $reservation->id, 'code' => $reservation->code, 'sale_id' => $saleId]);

        return $reservation;
    }

    /** Why a code cannot be redeemed right now, or null when it can. */
    public function redeemBlocker(MarketplaceReservation $reservation): ?string
    {
        return match (true) {
            $reservation->status === MarketplaceReservationStatus::Redeemed => 'Already redeemed on sale '.($reservation->sale_number ?? '#'.$reservation->sale_id).'.',
            $reservation->status === MarketplaceReservationStatus::Cancelled => 'The buyer cancelled this reservation.',
            $reservation->status === MarketplaceReservationStatus::Failed => 'The stock hold failed: '.($reservation->failed_reason ?? 'no stock').'.',
            $reservation->status === MarketplaceReservationStatus::Expired, $reservation->expires_at->isPast() => 'This reservation expired on '.$reservation->expires_at->timezone(config('app.timezone'))->format('D j M, g:i a').'.',
            $reservation->status === MarketplaceReservationStatus::Pending => 'The stock hold is still being confirmed. Try again in a minute.',
            default => null,
        };
    }

    public function listForCustomer(MarketplaceCustomer $customer, int $perPage = 20): LengthAwarePaginator
    {
        return MarketplaceReservation::on('central')->with('shop')
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @param  array{status?: string|null}  $filters
     */
    public function listForShop(string $tenantId, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = MarketplaceReservation::on('central')->forTenant($tenantId)->orderByDesc('created_at')->orderByDesc('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($perPage);
    }

    /** @param  array{product_slug?: string, product_id?: int}  $data */
    private function listedProduct(array $data): MarketplaceProduct
    {
        $query = MarketplaceProduct::on('central')->active()->fromListedShops();
        $product = isset($data['product_id'])
            ? $query->find((int) $data['product_id'])
            : $query->where('slug', (string) ($data['product_slug'] ?? ''))->first();

        if (! $product) {
            throw ValidationException::withMessages(['product' => ['That item is not available for reservation.']]);
        }

        return $product;
    }


    private function smsBuyer(MarketplaceReservation $reservation, string $message, string $template): void
    {
        if (! $reservation->buyer_phone) {
            return;
        }

        $this->messenger->sms($reservation->buyer_phone, $message, $template, $reservation->tenant_id);
    }

    private function itemLine(MarketplaceReservation $reservation): string
    {
        $qty = (float) $reservation->quantity;

        return ($qty == 1 ? '' : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.').' x ').$reservation->product_name;
    }

    private function shopName(MarketplaceReservation $reservation): string
    {
        return BusinessDetail::on('central')->where('tenant_id', $reservation->tenant_id)->value('business_name') ?? 'the shop';
    }
}
