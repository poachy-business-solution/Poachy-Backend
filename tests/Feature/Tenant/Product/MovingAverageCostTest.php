<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Services\Tenant\Inventory\ProductCostService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** F4: restocking at a different buying price keeps a moving weighted average cost per branch. */
class MovingAverageCostTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_restocks_at_different_prices_average_out_and_cost_each_sale(): void
    {
        $this->configureTenantHttpDatabases();
        Storage::fake('public');
        $domain = 'avg-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Food', 'slug' => 'food', 'created_at' => now(), 'updated_at' => now()]);
                $piece = DB::table('units_of_measure')->insertGetId(['code' => 'pc', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);
                $store = DB::table('stores')->insertGetId(['name' => 'Main branch', 'code' => 'MAIN', 'address' => 'Town', 'created_at' => now(), 'updated_at' => now()]);

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $uuid = $this->post($url('/api/v1/tenant/products'), ['name' => 'Sugar 1kg', 'category_id' => $cat, 'product_type' => 'simple',
                    'base_selling_price' => 200, 'base_uom_id' => $piece, 'cost_price' => 100, 'primary_image' => UploadedFile::fake()->image('p.jpg')],
                    ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
                $product = Product::query()->where('uuid', $uuid)->firstOrFail();
                $costs = app(ProductCostService::class);
                $receive = fn (array $body) => $this->postJson($url("/api/v1/tenant/products/{$uuid}/receive-stock"), ['store_id' => $store] + $body)->assertCreated();
                $inventory = fn () => Inventory::query()->where('store_id', $store)->where('product_id', $product->id)->whereNull('product_variant_id')->firstOrFail();

                // 10 @ 100 then 10 @ 140 → everything on hand averages 120.
                $receive(['quantity' => 10, 'unit_cost' => 100]);
                $this->assertSame('100.0000', $inventory()->average_cost);
                $receive(['quantity' => 10, 'unit_cost' => 140]);
                $this->assertSame('120.0000', $inventory()->average_cost);
                $this->assertSame(120.0, $costs->unitCost($product, $store));

                // A sale is costed at the average, not at the last price. (Aggregate jobs are queued in
                // production; run inline on the sync queue they would end tenancy mid-request.)
                Bus::fake();
                $this->postJson($url('/api/v1/tenant/sales'), ['store_id' => $store, 'items' => [['product_id' => $product->id, 'uom_id' => $piece, 'quantity' => 5]],
                    'payments' => [['method' => 'cash', 'amount' => 1000]]])->assertCreated();
                $this->assertSame('120.00', DB::table('sale_items')->where('product_id', $product->id)->value('unit_cost'));
                $this->assertSame('15.0000', $inventory()->quantity_on_hand);

                // A delivery with no price is costed at the current cost — the average does not fall to 0.
                $receive(['quantity' => 15]);
                $this->assertSame('120.0000', $inventory()->average_cost);
                $this->assertSame('120.00', DB::table('purchase_order_items')->where('product_id', $product->id)->orderByDesc('id')->value('unit_cost'));

                // 30 @ 120 on hand + 10 @ 200 → 140.
                $receive(['quantity' => 10, 'unit_cost' => 200]);
                $this->assertSame('140.0000', $inventory()->average_cost);

                // Product detail: average, entered price and the recent buying prices (newest first).
                $summary = $this->getJson($url("/api/v1/tenant/products/{$uuid}"))->assertOk()->json('data.cost_summary');
                $this->assertEquals(140, $summary['average_cost']);
                $this->assertEquals(100, $summary['entered_cost']);
                $this->assertEquals([200, 120, 140, 100], array_column($summary['recent'], 'unit_cost'));
                $this->assertEquals([10, 15, 10, 10], array_column($summary['recent'], 'quantity'));
                $this->assertSame('Main branch', $summary['recent'][0]['store_name']);

                // Stock from before averages were kept blends with its latest receipt price (200), not the entered price.
                Inventory::query()->whereKey($inventory()->id)->update(['average_cost' => null]);
                $receive(['quantity' => 10, 'unit_cost' => 100]);
                $this->assertSame('180.0000', $inventory()->average_cost);

                // Nothing on hand: the new delivery's price is the cost.
                Inventory::query()->whereKey($inventory()->id)->update(['quantity_on_hand' => 0, 'quantity_available' => 0]);
                $receive(['quantity' => 5, 'unit_cost' => 90]);
                $this->assertSame('90.0000', $inventory()->average_cost);

                // Cashiers never see what stock cost.
                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $this->assertArrayNotHasKey('cost_summary', $this->getJson($url("/api/v1/tenant/products/{$uuid}"))->assertOk()->json('data'));
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
