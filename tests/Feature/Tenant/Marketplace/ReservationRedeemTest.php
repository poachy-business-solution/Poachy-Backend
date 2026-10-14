<?php

namespace Tests\Feature\Tenant\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use App\Enums\Tenant\ReservationStatus;
use App\Jobs\Tenant\ProcessInboundReservationHold;
use App\Jobs\Tenant\ProcessInboundReservationRelease;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceReservation;
use App\Models\SyncQueueOutbound;
use App\Models\Tenant\Inventory;
use App\Models\Tenant\InventoryReservation;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Models\Tenant\Sale;
use App\Models\Tenant\Store;
use App\Models\Tenant\UnitOfMeasure;
use App\Models\User;
use App\Services\Tenant\Inventory\StockReservationService;
use App\Services\Tenant\Marketplace\MarketplaceOrderStaffNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * Item 32: reserve & collect, shop side, on a real tenant. The hold job reserves stock for 24 h and
 * reports to central; the cashier looks the code up on Pay and redeems it against the sale, which
 * releases the hold; owners see the list.
 */
class ReservationRedeemTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_hold_lookup_redeem_and_release_on_a_real_shop(): void
    {
        $this->configureTenantHttpDatabases();
        config(['services.central_api.token' => 'central-test-token', 'services.central_api.url' => 'http://poachy.test']);
        Queue::fake();
        $domain = 'reserve-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);
        $buyerUser = User::on('central')->create(['name' => 'Wanjiku Buyer', 'email' => 'buyer-'.uniqid().'@example.com', 'password' => bcrypt('Password123'), 'user_type' => 'customer']);
        $buyer = MarketplaceCustomer::on('central')->create(['user_id' => $buyerUser->id, 'customer_number' => 'MKT-'.uniqid(), 'phone' => '+254711000111', 'is_active' => true]);

        try {
            $tenant->run(function () use ($url, $tenant, $buyer) {
                Http::fake();
                $owner = $this->createTenantUserWithRole('owner');
                $cashier = $this->createTenantUserWithRole('cashier');
                $store = Store::query()->first() ?? Store::query()->create(['name' => 'Main', 'code' => 'MAIN', 'address' => 'Tom Mboya St', 'is_active' => true]);
                $uom = UnitOfMeasure::query()->where('is_base_unit', true)->first() ?? UnitOfMeasure::query()->create(['code' => 'pcs', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'is_active' => true]);
                $category = ProductCategory::query()->first() ?? ProductCategory::query()->create(['name' => 'Shoes', 'slug' => 'shoes', 'is_active' => true]);
                $product = Product::query()->create(['name' => 'Block heels', 'slug' => 'block-heels-'.uniqid(), 'sku' => 'BH-'.uniqid(), 'category_id' => $category->id, 'base_uom_id' => $uom->id, 'base_selling_price' => 2500, 'is_active' => true]);
                $inventory = Inventory::query()->create(['store_id' => $store->id, 'product_id' => $product->id, 'quantity_on_hand' => 3, 'quantity_reserved' => 0, 'quantity_available' => 3]);

                $reservation = MarketplaceReservation::on('central')->create([
                    'code' => MarketplaceReservation::generateCode(), 'tenant_id' => $tenant->id, 'customer_id' => $buyer->id,
                    'marketplace_product_id' => $this->centralListing($tenant->id, $product->id), 'tenant_product_id' => $product->id,
                    'product_name' => 'Block heels', 'quantity' => 2, 'unit_price' => 2500, 'uom_code' => 'pcs', 'status' => MarketplaceReservationStatus::Pending,
                    'ref' => 'qr', 'buyer_name' => 'Wanjiku Buyer', 'buyer_phone' => '+254711000111', 'expires_at' => now()->addDay(), 'idempotency_key' => (string) Str::uuid(),
                ]);
                $payload = ['reservation_id' => $reservation->id, 'code' => $reservation->code, 'tenant_product_id' => $product->id, 'tenant_variant_id' => null, 'tenant_bundle_id' => null,
                    'product_name' => 'Block heels', 'quantity' => 2.0, 'hold_minutes' => 1440, 'expires_at' => $reservation->expires_at->toIso8601String(), 'buyer' => ['name' => 'Wanjiku Buyer', 'phone' => '+254711000111'], '_outbound_sync_id' => 77];

                // The hold job reserves the stock for 24 h and reports "held" to central.
                (new ProcessInboundReservationHold($payload))->handle(app(StockReservationService::class), app(MarketplaceOrderStaffNotificationService::class));
                $hold = InventoryReservation::query()->where('reference_type', 'MarketplaceReservation')->where('reference_id', $reservation->id)->firstOrFail();
                $this->assertSame(ReservationStatus::ACTIVE, $hold->status);
                $this->assertEquals(2, $hold->quantity_reserved);
                $this->assertEqualsWithDelta(now()->addMinutes(1440)->timestamp, $hold->reserved_until->timestamp, 5);
                $this->assertEquals(1, $inventory->fresh()->quantity_available);
                $sent = Http::recorded()->map(fn ($pair) => $pair[0])->last();
                $this->assertNotNull($sent, 'No HTTP call to central was recorded.');
                $this->assertStringEndsWith('/api/v1/central/sync/inbound/reservation-confirmation', $sent->url());
                $this->assertSame(['tenant_id' => $tenant->id, 'reservation_id' => $reservation->id, 'status' => 'held', 'reason' => null, 'outbound_sync_id' => 77], $sent->data());

                // Running it again changes nothing (idempotent); a hold for more than is in stock fails with a reason.
                (new ProcessInboundReservationHold($payload))->handle(app(StockReservationService::class), app(MarketplaceOrderStaffNotificationService::class));
                $this->assertSame(1, InventoryReservation::query()->where('reference_id', $reservation->id)->count());
                (new ProcessInboundReservationHold(['reservation_id' => $reservation->id + 1000, 'quantity' => 5.0] + $payload))->handle(app(StockReservationService::class), app(MarketplaceOrderStaffNotificationService::class));
                $failedCall = Http::recorded()->map(fn ($pair) => $pair[0])->last();
                $this->assertSame('failed', $failedCall->data()['status']);
                $this->assertSame($reservation->id + 1000, $failedCall->data()['reservation_id']);
                $this->assertStringContainsString('Not enough stock', $failedCall->data()['reason']);

                // Central confirmed → active (done here directly; the HTTP call is faked above).
                $reservation->forceFill(['status' => MarketplaceReservationStatus::Active, 'held_at' => now()])->save();

                // Staff without selling rights see nothing; the cashier looks the code up on Pay.
                $this->actingAsTenant($this->createTenantUserWithoutRole());
                $this->getJson($url('/api/v1/tenant/reservations'))->assertForbidden();
                $this->getJson($url("/api/v1/tenant/reservations/{$reservation->code}"))->assertForbidden();
                $this->actingAsTenant($cashier);
                $this->getJson($url('/api/v1/tenant/reservations/PCH-ZZZZ'))->assertNotFound();
                $seen = $this->getJson($url('/api/v1/tenant/reservations/'.strtolower($reservation->code)))->assertOk()->json('data');
                $this->assertTrue($seen['redeemable']);
                $this->assertNull($seen['blocker']);
                $this->assertSame($product->id, $seen['item']['tenant_product_id']);
                $this->assertEquals(2, $seen['item']['quantity']);
                $this->assertSame('Wanjiku Buyer', $seen['buyer']['name']);
                $this->assertSame('own_link', $seen['source']);

                // Redeem needs a real sale; linking releases the hold (the sale already took the stock).
                $this->postJson($url("/api/v1/tenant/reservations/{$reservation->code}/redeem"), ['sale_id' => 999999])->assertUnprocessable();
                $sale = Sale::query()->create(['sale_number' => 'INV-TEST-'.uniqid(), 'store_id' => $store->id, 'sale_date' => now(), 'subtotal' => 5000, 'total_amount' => 5000, 'amount_paid' => 5000, 'served_by' => $cashier->id]);
                $redeemed = $this->postJson($url("/api/v1/tenant/reservations/{$reservation->code}/redeem"), ['sale_id' => $sale->id])->assertOk()->json('data');
                $this->assertSame('redeemed', $redeemed['status']);
                $this->assertSame($sale->id, $redeemed['sale_id']);
                $this->assertSame($sale->sale_number, $redeemed['sale_number']);
                $fresh = $reservation->fresh();
                $this->assertSame(MarketplaceReservationStatus::Redeemed, $fresh->status);
                $this->assertSame($cashier->id, $fresh->redeemed_by);
                $this->assertSame(ReservationStatus::CANCELLED, $hold->fresh()->status);
                $this->assertEquals(3, $inventory->fresh()->quantity_available);
                $this->assertEquals(0, $inventory->fresh()->quantity_reserved);

                // Same sale again → no-op 200; a different sale → 409; another code on the same sale → 409.
                $this->postJson($url("/api/v1/tenant/reservations/{$reservation->code}/redeem"), ['sale_id' => $sale->id])->assertOk()->assertJsonPath('data.status', 'redeemed');
                $otherSale = Sale::query()->create(['sale_number' => 'INV-TEST-'.uniqid(), 'store_id' => $store->id, 'sale_date' => now(), 'subtotal' => 100, 'total_amount' => 100, 'amount_paid' => 100, 'served_by' => $cashier->id]);
                $this->postJson($url("/api/v1/tenant/reservations/{$reservation->code}/redeem"), ['sale_id' => $otherSale->id])->assertConflict();
                $expired = MarketplaceReservation::on('central')->create([
                    'code' => MarketplaceReservation::generateCode(), 'tenant_id' => $tenant->id, 'customer_id' => $buyer->id, 'marketplace_product_id' => $fresh->marketplace_product_id, 'tenant_product_id' => $product->id,
                    'product_name' => 'Block heels', 'quantity' => 1, 'unit_price' => 2500, 'status' => MarketplaceReservationStatus::Active, 'held_at' => now()->subDay(), 'expires_at' => now()->subMinute(), 'idempotency_key' => (string) Str::uuid(),
                ]);
                $this->getJson($url("/api/v1/tenant/reservations/{$expired->code}"))->assertOk()->assertJsonPath('data.redeemable', false)->assertJsonPath('data.blocker', fn ($b) => str_starts_with($b, 'This reservation expired on'));
                $this->postJson($url("/api/v1/tenant/reservations/{$expired->code}/redeem"), ['sale_id' => $otherSale->id])->assertConflict();
                $active = MarketplaceReservation::on('central')->create([
                    'code' => MarketplaceReservation::generateCode(), 'tenant_id' => $tenant->id, 'customer_id' => $buyer->id, 'marketplace_product_id' => $fresh->marketplace_product_id, 'tenant_product_id' => $product->id,
                    'product_name' => 'Block heels', 'quantity' => 1, 'unit_price' => 2500, 'status' => MarketplaceReservationStatus::Active, 'held_at' => now(), 'expires_at' => now()->addDay(), 'idempotency_key' => (string) Str::uuid(),
                ]);
                $this->postJson($url("/api/v1/tenant/reservations/{$active->code}/redeem"), ['sale_id' => $sale->id])->assertConflict();

                // Release job (buyer cancelled / expired) frees a live hold and acks central.
                (new ProcessInboundReservationHold(['reservation_id' => $active->id, 'code' => $active->code, 'quantity' => 1.0] + $payload))->handle(app(StockReservationService::class), app(MarketplaceOrderStaffNotificationService::class));
                $this->assertEquals(2, $inventory->fresh()->quantity_available);
                (new ProcessInboundReservationRelease(['reservation_id' => $active->id, 'reason' => 'Buyer cancelled', '_outbound_sync_id' => 78]))->handle(app(StockReservationService::class));
                $this->assertEquals(3, $inventory->fresh()->quantity_available);
                $ack = Http::recorded()->map(fn ($pair) => $pair[0])->last();
                $this->assertStringEndsWith('/api/v1/central/sync/inbound/outbound-sync-ack', $ack->url());
                $this->assertSame(78, $ack->data()['outbound_sync_id']);
                $this->assertSame('completed', $ack->data()['status']);

                // The normal path: the sale itself carries the code → the hold becomes sellable stock,
                // the sale takes it, the reservation is linked. Wrong or used codes are 422 with a reason.
                $viaSale = MarketplaceReservation::on('central')->create([
                    'code' => MarketplaceReservation::generateCode(), 'tenant_id' => $tenant->id, 'customer_id' => $buyer->id, 'marketplace_product_id' => $fresh->marketplace_product_id, 'tenant_product_id' => $product->id,
                    'product_name' => 'Block heels', 'quantity' => 3, 'unit_price' => 2500, 'status' => MarketplaceReservationStatus::Pending, 'expires_at' => now()->addDay(), 'idempotency_key' => (string) Str::uuid(),
                ]);
                (new ProcessInboundReservationHold(['reservation_id' => $viaSale->id, 'code' => $viaSale->code, 'quantity' => 3.0] + $payload))->handle(app(StockReservationService::class), app(MarketplaceOrderStaffNotificationService::class));
                $viaSale->forceFill(['status' => MarketplaceReservationStatus::Active, 'held_at' => now()])->save();
                $this->assertEquals(0, $inventory->fresh()->quantity_available); // all three held
                $this->actingAsTenant($cashier);
                $saleBody = ['store_id' => $store->id, 'items' => [['product_id' => $product->id, 'uom_id' => $uom->id, 'quantity' => 3]], 'payments' => [['method' => 'cash', 'amount' => 7500]]];
                $this->postJson($url('/api/v1/tenant/sales'), $saleBody + ['idempotency_key' => (string) Str::uuid()])->assertStatus(422); // held stock is not free stock
                $this->postJson($url('/api/v1/tenant/sales'), $saleBody + ['idempotency_key' => (string) Str::uuid(), 'reservation_code' => 'PCH-ZZZZ'])->assertStatus(422)->assertJsonPath('message', 'No reservation with that code at this shop.');
                $this->postJson($url('/api/v1/tenant/sales'), $saleBody + ['idempotency_key' => (string) Str::uuid(), 'reservation_code' => $reservation->code])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Already redeemed on sale'));
                $this->postJson($url('/api/v1/tenant/sales'), ['store_id' => $store->id, 'items' => [['product_id' => $product->id + 100000, 'uom_id' => $uom->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1]], 'idempotency_key' => (string) Str::uuid(), 'reservation_code' => $viaSale->code])->assertStatus(422);
                $this->assertEquals(0, $inventory->fresh()->quantity_available); // failed attempts kept the hold
                $saleId = $this->postJson($url('/api/v1/tenant/sales'), $saleBody + ['idempotency_key' => (string) Str::uuid(), 'reservation_code' => strtolower($viaSale->code)])->assertCreated()->json('data.sale.id');
                $linked = $viaSale->fresh();
                $this->assertSame(MarketplaceReservationStatus::Redeemed, $linked->status);
                $this->assertSame($saleId, $linked->sale_id);
                $this->assertSame($cashier->id, $linked->redeemed_by);
                $this->assertEquals(0, $inventory->fresh()->quantity_reserved);
                $this->assertEquals(0, $inventory->fresh()->quantity_on_hand); // sold, not double-deducted
                $this->assertSame(ReservationStatus::CANCELLED, InventoryReservation::query()->where('reference_id', $viaSale->id)->value('status'));

                // Owners and managers see the list for the Marketplace tab.
                $this->actingAsTenant($owner);
                $list = $this->getJson($url('/api/v1/tenant/reservations?status=redeemed'))->assertOk()->json('data');
                $this->assertSame([$viaSale->code, $reservation->code], array_column($list['reservations'], 'code'));
                $this->assertSame(2, $list['counts']['redeemed_today']);
                $this->assertSame(1, $list['counts']['open']);
                $this->assertSame(4, $this->getJson($url('/api/v1/tenant/reservations'))->json('data.pagination.total'));
            });
        } finally {
            MarketplaceReservation::on('central')->where('tenant_id', $tenant->id)->delete();
            SyncQueueOutbound::on('central')->where('tenant_id', $tenant->id)->delete();
            MarketplaceProduct::on('central')->where('tenant_id', $tenant->id)->forceDelete();
            $buyer->forceDelete();
            $buyerUser->forceDelete();
            $this->deleteTenantHttpFixture($domain);
        }
    }

    private function centralListing(string $tenantId, int $tenantProductId): int
    {
        return MarketplaceProduct::on('central')->create([
            'tenant_id' => $tenantId, 'tenant_product_id' => $tenantProductId, 'name' => 'Block heels', 'slug' => 'block-heels-'.strtolower(uniqid()), 'sku' => 'SKU-'.uniqid(),
            'online_price' => 2500, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece', 'tax_rate' => 0, 'available_quantity' => 3, 'stock_status' => 'in_stock', 'is_active' => true,
        ])->id;
    }
}
