<?php

namespace Tests\Feature\Central\Marketplace;

use App\Enums\Central\MarketplaceReservationStatus;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceProduct;
use App\Models\MarketplaceReservation;
use App\Models\MessageLog;
use App\Models\SyncQueueOutbound;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Feature\Central\Concerns\InteractsWithCentralHttpAuth;
use Tests\TestCase;

/**
 * Item 32: reserve & collect, central side. A buyer holds an item for 24 h with a PCH- code; the
 * hold is queued to the shop; the shop's answer moves the code to active/failed and texts the buyer;
 * cancel and expiry release the stock.
 */
class MarketplaceReservationTest extends TestCase
{
    use InteractsWithCentralHttpAuth;

    private string $run;

    private string $tenantId;

    private string $hiddenTenantId;

    private MarketplaceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('services.central_api.token', 'central-test-token');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Cache::flush();
        Queue::fake();

        $this->run = strtolower(uniqid());
        $this->category = MarketplaceCategory::on('central')->create(['name' => 'Reserve '.$this->run, 'slug' => 'reserve-'.$this->run, 'is_active' => true]);
        $this->tenantId = $this->shop('listed', true);
        $this->hiddenTenantId = $this->shop('hidden', false);
    }

    protected function tearDown(): void
    {
        $ids = [$this->tenantId, $this->hiddenTenantId];
        MarketplaceReservation::on('central')->whereIn('tenant_id', $ids)->delete();
        SyncQueueOutbound::on('central')->whereIn('tenant_id', $ids)->delete();
        MessageLog::on('central')->whereIn('tenant_id', $ids)->delete();
        MarketplaceCustomer::on('central')->whereIn('user_id', $this->centralHttpUserIds)->forceDelete();
        MarketplaceProduct::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        BusinessDetail::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        DB::connection('central')->table('domains')->whereIn('tenant_id', $ids)->delete();
        DB::connection('central')->table('tenants')->whereIn('id', $ids)->delete();
        $this->category->delete();
        $this->cleanupCentralHttpUsers();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_buyer_reserves_an_item_the_shop_confirms_and_the_code_is_texted(): void
    {
        $buyer = $this->createCentralUserWithRole('customer');
        $this->createCentralCustomerProfile($buyer);
        $product = $this->listing($this->tenantId, 'Block heels', 2500, ['available_quantity' => 3]);

        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 1])->assertUnauthorized();

        $this->actingAsCentral($buyer);
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 1])->assertStatus(400);

        $key = (string) Str::uuid();
        $created = $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 2, 'ref' => 'WA'], ['Idempotency-Key' => $key])
            ->assertCreated()->json('data');
        $this->assertMatchesRegularExpression('/^PCH-[A-HJ-NP-Z2-9]{4,5}$/', $created['code']);
        $this->assertSame('pending', $created['status']);
        $this->assertEquals(2, $created['product']['quantity']);
        $this->assertEquals(5000, $created['product']['total']);
        $this->assertSame('Listed Duka '.$this->run, $created['shop']['name']);
        $this->assertTrue($created['can_cancel']);

        $reservation = MarketplaceReservation::on('central')->where('code', $created['code'])->firstOrFail();
        $this->assertSame('wa', $reservation->ref);
        $this->assertSame('own_link', $reservation->source());
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $reservation->expires_at->timestamp, 5);

        $hold = SyncQueueOutbound::on('central')->where('syncable_type', 'marketplace_reservation')->where('syncable_id', $reservation->id)->where('action', 'reservation_hold')->firstOrFail();
        $this->assertSame($product->tenant_product_id, $hold->payload['tenant_product_id']);
        $this->assertEquals(2, $hold->payload['quantity']);
        $this->assertSame(1440, $hold->payload['hold_minutes']);
        $this->assertSame($created['code'], $hold->payload['code']);

        // Same key → same reservation, nothing new queued.
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 2], ['Idempotency-Key' => $key])->assertOk()->assertJsonPath('data.code', $created['code']);
        $this->assertSame(1, MarketplaceReservation::on('central')->where('customer_id', $reservation->customer_id)->count());

        // A second open hold on the same item is refused; too many is refused; unlisted shops and no stock are refused.
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 1], ['Idempotency-Key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('product');
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 11], ['Idempotency-Key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $hidden = $this->listing($this->hiddenTenantId, 'Hidden heels', 900);
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $hidden->slug, 'quantity' => 1], ['Idempotency-Key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('product');
        $empty = $this->listing($this->tenantId, 'Sold out sandals', 700, ['available_quantity' => 0, 'stock_status' => 'out_of_stock']);
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_id' => $empty->id, 'quantity' => 1], ['Idempotency-Key' => Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors('quantity');

        // The shop holds the stock → active, and the buyer gets the code by SMS.
        $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/reservation-confirmation', [
            'tenant_id' => $this->hiddenTenantId, 'reservation_id' => $reservation->id, 'status' => 'held',
        ])->assertForbidden();
        $this->withToken('wrong')->postJson('/api/v1/central/sync/inbound/reservation-confirmation', [
            'tenant_id' => $this->tenantId, 'reservation_id' => $reservation->id, 'status' => 'held',
        ])->assertForbidden();
        $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/reservation-confirmation', [
            'tenant_id' => $this->tenantId, 'reservation_id' => $reservation->id, 'status' => 'held', 'outbound_sync_id' => $hold->id,
        ])->assertStatus(202)->assertJsonPath('data.status', 'active');
        $this->assertTrue($reservation->fresh()->isRedeemable());
        $sms = MessageLog::on('central')->where('template', 'reservation_held')->where('tenant_id', $this->tenantId)->latest('id')->firstOrFail();
        $this->assertStringContainsString($created['code'], $sms->subject);
        $this->assertSame('+254700000000', $sms->recipient);

        // Mine: list + show; someone else's code is 404.
        $this->actingAsCentral($buyer);
        $this->getJson('/api/v1/central/marketplace/reservations')->assertOk()->assertJsonPath('data.reservations.0.code', $created['code'])->assertJsonPath('data.reservations.0.status', 'active');
        $this->getJson('/api/v1/central/marketplace/reservations/'.strtolower($created['code']))->assertOk()->assertJsonPath('data.status_label', 'Held for you');
        $other = $this->createCentralUserWithRole('customer');
        MarketplaceCustomer::on('central')->create(['user_id' => $other->id, 'customer_number' => 'MKT-'.uniqid(), 'phone' => '+2547'.random_int(10000000, 99999999), 'is_active' => true]);
        $this->actingAsCentral($other);
        $this->getJson('/api/v1/central/marketplace/reservations/'.$created['code'])->assertNotFound();
        $this->postJson('/api/v1/central/marketplace/reservations/'.$created['code'].'/cancel')->assertNotFound();

        // The buyer cancels → the shop is told to release the stock.
        $this->actingAsCentral($buyer);
        $this->postJson('/api/v1/central/marketplace/reservations/'.$created['code'].'/cancel')->assertOk()->assertJsonPath('data.status', 'cancelled')->assertJsonPath('data.can_cancel', false);
        $this->assertTrue(SyncQueueOutbound::on('central')->where('syncable_id', $reservation->id)->where('action', 'reservation_release')->exists());
        $this->postJson('/api/v1/central/marketplace/reservations/'.$created['code'].'/cancel')->assertUnprocessable();
    }

    public function test_failed_holds_and_expiry_release_the_stock_and_tell_the_buyer(): void
    {
        $buyer = $this->createCentralUserWithRole('customer');
        $this->createCentralCustomerProfile($buyer);
        $this->actingAsCentral($buyer);
        $product = $this->listing($this->tenantId, 'Rice 2kg', 320, ['available_quantity' => 10]);

        $failing = $this->reserve($product);
        $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/reservation-confirmation', [
            'tenant_id' => $this->tenantId, 'reservation_id' => $failing->id, 'status' => 'failed', 'reason' => 'Not enough stock of Rice 2kg.',
        ])->assertStatus(202)->assertJsonPath('data.status', 'failed');
        $this->assertSame('Not enough stock of Rice 2kg.', $failing->fresh()->failed_reason);
        $this->assertTrue(MessageLog::on('central')->where('template', 'reservation_failed')->where('tenant_id', $this->tenantId)->exists());
        $this->getJson('/api/v1/central/marketplace/reservations/'.$failing->code)->assertOk()->assertJsonPath('data.can_cancel', false);

        // A failed hold frees the per-item slot, so the buyer may try again.
        $again = $this->reserve($product);
        $this->withToken('central-test-token')->postJson('/api/v1/central/sync/inbound/reservation-confirmation', [
            'tenant_id' => $this->tenantId, 'reservation_id' => $again->id, 'status' => 'held',
        ])->assertStatus(202);

        // 24 h pass → expired, shop told to release; the command is idempotent.
        $again->forceFill(['expires_at' => now()->subMinute()])->save();
        Artisan::call('marketplace:expire-reservations');
        $this->assertSame(MarketplaceReservationStatus::Expired, $again->fresh()->status);
        $this->assertSame(1, SyncQueueOutbound::on('central')->where('syncable_id', $again->id)->where('action', 'reservation_release')->count());
        Artisan::call('marketplace:expire-reservations');
        $this->assertSame(1, SyncQueueOutbound::on('central')->where('syncable_id', $again->id)->where('action', 'reservation_release')->count());
        $this->postJson('/api/v1/central/marketplace/reservations/'.$again->code.'/cancel')->assertUnprocessable();

        // Five open holds is the ceiling.
        for ($i = 0; $i < 5; $i++) {
            $this->reserve($this->listing($this->tenantId, "Item {$i}", 100));
        }
        $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $this->listing($this->tenantId, 'Sixth', 100)->slug, 'quantity' => 1], ['Idempotency-Key' => Str::uuid()])
            ->assertUnprocessable()->assertJsonPath('errors.product.0', 'You already have 5 items on hold. Collect or cancel one first.');
    }

    private function reserve(MarketplaceProduct $product): MarketplaceReservation
    {
        $code = $this->postJson('/api/v1/central/marketplace/reservations', ['product_slug' => $product->slug, 'quantity' => 1], ['Idempotency-Key' => Str::uuid()])
            ->assertCreated()->json('data.code');

        return MarketplaceReservation::on('central')->where('code', $code)->firstOrFail();
    }

    private function shop(string $key, bool $verified): string
    {
        $id = "reserve-{$key}-{$this->run}";
        DB::connection('central')->table('tenants')->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        DB::connection('central')->table('domains')->insert(['domain' => "{$id}.poachy.test", 'tenant_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        BusinessDetail::on('central')->create(['tenant_id' => $id, 'business_name' => ucfirst($key).' Duka '.$this->run, 'business_phone' => '+254700000'.random_int(100, 999),
            'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
            'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
            'city' => 'Nairobi', 'county' => 'Nairobi', 'status' => 'active', 'is_verified' => $verified, 'verified_at' => $verified ? now() : null, 'latitude' => -1.28, 'longitude' => 36.82]);

        return $id;
    }

    /** @param  array<string, mixed>  $extra */
    private function listing(string $tenantId, string $name, float $price, array $extra = []): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create($extra + [
            'tenant_id' => $tenantId, 'tenant_product_id' => random_int(1000, 9999), 'name' => $name, 'slug' => Str::slug($name).'-'.strtolower(uniqid()), 'sku' => 'SKU-'.uniqid(),
            'marketplace_category_id' => $this->category->id, 'online_price' => $price, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece',
            'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true, 'primary_image' => "http://localhost/img/{$name}.png",
        ]);
    }
}
