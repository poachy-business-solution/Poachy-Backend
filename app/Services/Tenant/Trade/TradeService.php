<?php

namespace App\Services\Tenant\Trade;

use App\Enums\Tenant\CashMovementReason;
use App\Enums\Tenant\InventoryMovementType;
use App\Enums\Tenant\SupplierType;
use App\Enums\Tenant\TradeDirection;
use App\Enums\Tenant\TradeEventType;
use App\Models\Tenant\CashMovement;
use App\Models\Tenant\Product;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\TradeEvent;
use App\Models\Tenant\TradeLine;
use App\Models\Tenant\TradePartner;
use App\Models\Tenant\User;
use App\Services\Tenant\Inventory\InventoryMovementService;
use App\Services\Tenant\Inventory\ProductCostService;
use App\Services\Tenant\Product\ProductStockReceivingService;
use App\Services\Tenant\Shift\TillService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Shop-to-shop trading, recorded on this shop's side only.
 *
 * - They took goods: stock leaves at my cost; a "lent" line opens at the
 *   agreed price.
 * - I took goods: stock arrives with the agreed price as its cost (received
 *   from the partner's supplier record); a "borrowed" line opens.
 * - Goods brought back (either way, partial ok) reverse the stock.
 * - Money paid (either way, cash or M-Pesa) settles open lines oldest
 *   first; cash goes through the till drawer.
 */
class TradeService
{
    public function __construct(
        protected InventoryMovementService $inventoryMovements,
        protected ProductStockReceivingService $stockReceiving,
        protected TillService $tillService,
    ) {}

    /**
     * They took goods from me.
     *
     * @param  array<int, array{product_id: int, quantity: float|string, unit_price: float|string}>  $items
     * @return Collection<int, TradeLine>
     */
    public function recordGoodsOut(TradePartner $partner, User $user, int $storeId, array $items, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($partner, $user, $storeId, $items, $note) {
            return collect($items)->map(function (array $item) use ($partner, $user, $storeId, $note) {
                $product = $this->tradableProduct((int) $item['product_id']);
                $quantity = (float) $item['quantity'];
                $cost = $this->myCost($storeId, $product->id);

                $line = TradeLine::create([
                    'trade_partner_id' => $partner->id,
                    'store_id' => $storeId,
                    'direction' => TradeDirection::LENT,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $cost,
                    'opened_at' => now(),
                    'recorded_by' => $user->id,
                    'note' => $note,
                ]);

                $this->moveStock($storeId, $product, -$quantity, $cost, $line, "{$partner->name} took goods");
                $this->logEvent($partner, $user, $storeId, TradeEventType::THEY_TOOK, $quantity * (float) $item['unit_price'], $line, $note);

                return $line->load('product');
            });
        });
    }

    /**
     * I took goods from them. Their agreed price becomes the stock's cost.
     *
     * @param  array<int, array{product_id: int, quantity: float|string, unit_price: float|string}>  $items
     * @return Collection<int, TradeLine>
     */
    public function recordGoodsIn(TradePartner $partner, User $user, int $storeId, array $items, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($partner, $user, $storeId, $items, $note) {
            $supplierId = $this->supplierFor($partner)->id;

            return collect($items)->map(function (array $item) use ($partner, $user, $storeId, $note, $supplierId) {
                $product = $this->tradableProduct((int) $item['product_id']);
                $quantity = (float) $item['quantity'];

                $this->stockReceiving->receive($product, [
                    'store_id' => $storeId,
                    'quantity' => $quantity,
                    'unit_cost' => (float) $item['unit_price'],
                    'supplier_id' => $supplierId,
                    'notes' => "Took from {$partner->name}",
                ]);

                $line = TradeLine::create([
                    'trade_partner_id' => $partner->id,
                    'store_id' => $storeId,
                    'direction' => TradeDirection::BORROWED,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $item['unit_price'],
                    'unit_cost' => $item['unit_price'],
                    'opened_at' => now(),
                    'recorded_by' => $user->id,
                    'note' => $note,
                ]);

                $this->logEvent($partner, $user, $storeId, TradeEventType::I_TOOK, $quantity * (float) $item['unit_price'], $line, $note);

                return $line->load('product');
            });
        });
    }

    /**
     * Goods brought back on open lines — by them (lent lines) or by me
     * (borrowed lines).
     *
     * @param  array<int, array{trade_line_id: int, quantity: float|string}>  $returns
     * @return Collection<int, TradeLine>
     *
     * @throws ValidationException
     */
    public function recordReturn(TradePartner $partner, User $user, int $storeId, array $returns, ?string $note = null): Collection
    {
        return DB::transaction(function () use ($partner, $user, $storeId, $returns, $note) {
            return collect($returns)->map(function (array $return, int $index) use ($partner, $user, $storeId, $note) {
                $line = TradeLine::query()
                    ->where('trade_partner_id', $partner->id)
                    ->lockForUpdate()
                    ->findOrFail($return['trade_line_id']);
                $quantity = (float) $return['quantity'];

                if ($line->status !== TradeLine::STATUS_OPEN || $quantity > $line->outstandingQuantity() + 0.00005) {
                    throw ValidationException::withMessages([
                        "returns.{$index}.quantity" => 'Only '.rtrim(rtrim(number_format($line->outstandingQuantity(), 4, '.', ''), '0'), '.').' can be brought back on this line.',
                    ]);
                }

                $owedAfter = ($line->outstandingQuantity() - $quantity) * (float) $line->unit_price - (float) $line->amount_paid;

                if ($owedAfter < -0.005) {
                    throw ValidationException::withMessages([
                        "returns.{$index}.quantity" => 'These goods were already paid for. Record money paid back instead.',
                    ]);
                }

                $line->quantity_returned = (float) $line->quantity_returned + $quantity;
                $line->settleIfDone();
                $line->save();

                $product = $line->product;
                $lent = $line->direction === TradeDirection::LENT;
                $this->moveStock(
                    $storeId,
                    $product,
                    $lent ? $quantity : -$quantity,
                    $line->unit_cost !== null ? (float) $line->unit_cost : null,
                    $line,
                    $lent ? "{$partner->name} brought goods back" : "Goods taken back to {$partner->name}",
                );
                $this->logEvent(
                    $partner,
                    $user,
                    $storeId,
                    $lent ? TradeEventType::RETURNED_TO_ME : TradeEventType::RETURNED_BY_ME,
                    $quantity * (float) $line->unit_price,
                    $line,
                    $note,
                    $quantity,
                );

                return $line->load('product');
            });
        });
    }

    /**
     * Money paid either way. Settles that side's open lines oldest first;
     * cash goes into or out of the recorder's till drawer.
     *
     * @param  string  $direction  'to_me' (they paid me) or 'by_me' (I paid them)
     *
     * @throws ValidationException
     */
    public function recordPayment(TradePartner $partner, User $user, int $storeId, string $direction, float $amount, string $method, ?string $reference = null, ?string $note = null): TradeEvent
    {
        return DB::transaction(function () use ($partner, $user, $storeId, $direction, $amount, $method, $reference, $note) {
            $toMe = $direction === 'to_me';
            $lines = TradeLine::query()
                ->where('trade_partner_id', $partner->id)
                ->where('direction', $toMe ? TradeDirection::LENT : TradeDirection::BORROWED)
                ->where('status', TradeLine::STATUS_OPEN)
                ->orderBy('opened_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $owed = round($lines->sum(fn (TradeLine $line) => $line->outstandingAmount()), 2);

            if ($amount > $owed + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => $owed <= 0
                        ? ($toMe ? "{$partner->name} doesn't owe you anything." : "You don't owe {$partner->name} anything.")
                        : 'That is more than '.($toMe ? "{$partner->name} owes you" : "you owe {$partner->name}").' (KSh '.number_format($owed, 2).').',
                ]);
            }

            $left = $amount;
            foreach ($lines as $line) {
                if ($left <= 0.005) {
                    break;
                }
                $applied = min($left, $line->outstandingAmount());
                $line->amount_paid = round((float) $line->amount_paid + $applied, 2);
                $line->settleIfDone();
                $line->save();
                $left = round($left - $applied, 2);
            }

            $cashMovement = null;
            if ($method === 'cash') {
                $session = $this->tillService->sessionForSale($user->id, $storeId);
                $reason = $toMe ? CashMovementReason::TRADE_PAYMENT_IN : CashMovementReason::TRADE_PAYMENT_OUT;
                $cashMovement = CashMovement::create([
                    'store_id' => $storeId,
                    'shift_assignment_id' => $session?->id,
                    'recorded_by' => $user->id,
                    'direction' => $reason->direction(),
                    'reason' => $reason,
                    'amount' => $amount,
                    'note' => $partner->name,
                    'occurred_at' => now(),
                ]);
            }

            return TradeEvent::create([
                'trade_partner_id' => $partner->id,
                'store_id' => $storeId,
                'type' => $toMe ? TradeEventType::PAID_TO_ME : TradeEventType::PAID_BY_ME,
                'amount' => $amount,
                'payment_method' => $method,
                'payment_reference' => $reference,
                'cash_movement_id' => $cashMovement?->id,
                'recorded_by' => $user->id,
                'occurred_at' => now(),
                'note' => $note,
            ]);
        });
    }

    /**
     * Price suggestion for the next trade: the last price agreed with this
     * partner for the product, and what it costs me.
     *
     * @return array{last_price: float|null, last_direction: string|null, last_traded_at: string|null, my_cost: float|null}
     */
    public function priceHint(TradePartner $partner, int $productId, int $storeId): array
    {
        $last = TradeLine::query()
            ->where('trade_partner_id', $partner->id)
            ->where('product_id', $productId)
            ->latest('opened_at')
            ->latest('id')
            ->first();

        return [
            'last_price' => $last ? (float) $last->unit_price : null,
            'last_direction' => $last?->direction->value,
            'last_traded_at' => $last?->opened_at?->toISOString(),
            'my_cost' => $this->myCost($storeId, $productId),
        ];
    }

    /**
     * What a unit of the product cost me at the branch, or null when unknown.
     */
    public function myCost(int $storeId, int $productId): ?float
    {
        $cost = app(ProductCostService::class)->unitCost(Product::query()->findOrFail($productId), $storeId);

        return $cost === null ? null : round($cost, 2);
    }

    /**
     * @throws ValidationException
     */
    protected function tradableProduct(int $productId): Product
    {
        $product = Product::query()->findOrFail($productId);

        if ($product->requiresSerialTracking()) {
            throw ValidationException::withMessages([
                'items' => "{$product->name} is tracked by serial number and can't be lent or borrowed yet.",
            ]);
        }

        return $product;
    }

    /**
     * @throws ValidationException when there isn't enough stock to lend
     */
    protected function moveStock(int $storeId, Product $product, float $quantity, ?float $unitCost, TradeLine $line, string $note): void
    {
        try {
            $this->inventoryMovements->recordMovement([
                'store_id' => $storeId,
                'product_id' => $product->id,
                'uom_id' => $product->base_uom_id,
                'movement_type' => $quantity < 0 ? InventoryMovementType::TRANSFER_OUT : InventoryMovementType::TRANSFER_IN,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'reference_type' => 'TradeLine',
                'reference_id' => $line->id,
                'notes' => $note,
            ]);
        } catch (\RuntimeException $e) {
            if (str_starts_with($e->getMessage(), 'Insufficient stock')) {
                throw ValidationException::withMessages([
                    'items' => "Not enough {$product->name} in stock to give.",
                ]);
            }

            throw $e;
        }
    }

    protected function logEvent(TradePartner $partner, User $user, int $storeId, TradeEventType $type, float $amount, TradeLine $line, ?string $note, ?float $quantity = null): TradeEvent
    {
        return TradeEvent::create([
            'trade_partner_id' => $partner->id,
            'store_id' => $storeId,
            'trade_line_id' => $line->id,
            'type' => $type,
            'product_id' => $line->product_id,
            'quantity' => $quantity ?? (float) $line->quantity,
            'amount' => round($amount, 2),
            'recorded_by' => $user->id,
            'occurred_at' => now(),
            'note' => $note,
        ]);
    }

    /**
     * Stock received from a partner needs a supplier record: one per
     * partner, created the first time I take their goods.
     */
    protected function supplierFor(TradePartner $partner): Supplier
    {
        if ($partner->supplier_id && $supplier = Supplier::query()->find($partner->supplier_id)) {
            return $supplier;
        }

        $supplier = Supplier::query()->create([
            'name' => $partner->name,
            'supplier_type' => SupplierType::RETAILER->value,
            'phone' => $partner->phone,
            'notes' => 'Neighbour shop (trade partner) — goods borrowed from them are received from here.',
            'is_active' => true,
        ]);
        $partner->update(['supplier_id' => $supplier->id]);

        return $supplier;
    }
}
