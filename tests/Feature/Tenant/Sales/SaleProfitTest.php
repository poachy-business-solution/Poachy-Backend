<?php

namespace Tests\Feature\Tenant\Sales;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** F5: owners and managers see each sale's cost and profit; unknown costs are flagged; cashiers see neither. */
class SaleProfitTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_profit_per_sale_and_per_line_with_unknown_costs_flagged(): void
    {
        $this->configureTenantHttpDatabases();
        Storage::fake('public');
        $domain = 'profit-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Food', 'slug' => 'food', 'created_at' => now(), 'updated_at' => now()]);
                $piece = DB::table('units_of_measure')->insertGetId(['code' => 'pc', 'name' => 'Piece', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);
                $store = DB::table('stores')->insertGetId(['name' => 'Main branch', 'code' => 'MAIN', 'address' => 'Town', 'created_at' => now(), 'updated_at' => now()]);

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $create = fn (array $body) => $this->post($url('/api/v1/tenant/products'), $body + ['category_id' => $cat, 'product_type' => 'simple',
                    'base_uom_id' => $piece, 'primary_image' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
                $sugar = $create(['name' => 'Sugar 1kg', 'base_selling_price' => 200, 'cost_price' => 100]);
                $matches = $create(['name' => 'Matches', 'base_selling_price' => 50]);
                foreach ([$sugar, $matches] as $id) {
                    $uuid = DB::table('products')->where('id', $id)->value('uuid');
                    $this->postJson($url("/api/v1/tenant/products/{$uuid}/receive-stock"), ['store_id' => $store, 'quantity' => 10])->assertCreated();
                }
                // Matches arrived with no price and have no buying price: their cost stays unknown (not 0).
                $this->assertNull(DB::table('inventory')->where('product_id', $matches)->value('average_cost'));
                $this->assertSame('100.0000', DB::table('inventory')->where('product_id', $sugar)->value('average_cost'));

                // Aggregate jobs are queued in production; run inline on the sync queue they would end tenancy mid-request.
                Bus::fake();
                $sale = $this->postJson($url('/api/v1/tenant/sales?include_profit=1'), ['store_id' => $store,
                    'items' => [['product_id' => $sugar, 'uom_id' => $piece, 'quantity' => 2], ['product_id' => $matches, 'uom_id' => $piece, 'quantity' => 1]],
                    'payments' => [['method' => 'cash', 'amount' => 450]]])->assertCreated()->json('data.sale');

                $this->assertEquals(['total_cost' => 200, 'total_revenue' => 450, 'profit' => 250, 'margin_percentage' => 55.56, 'lines_without_cost' => 1, 'complete' => false], $sale['profit_margin']);
                $this->assertEquals([100, 200, true], [$sale['items'][0]['unit_cost'], $sale['items'][0]['profit'], $sale['items'][0]['cost_known']]);
                $this->assertSame([null, null, false], [$sale['items'][1]['unit_cost'], $sale['items'][1]['profit'], $sale['items'][1]['cost_known']]);
                $this->assertNull(DB::table('sale_items')->where('product_id', $matches)->value('unit_cost'));

                // Sale detail and the sales list carry the same figures when asked for them.
                $detail = $this->getJson($url("/api/v1/tenant/sales/{$sale['id']}?include_profit=1"))->assertOk()->json('data.sale');
                $this->assertEquals(250, $detail['profit_margin']['profit']);
                $this->assertEquals(200, $detail['items'][0]['profit']);
                $list = $this->getJson($url('/api/v1/tenant/sales?include_profit=1'))->assertOk()->json('data.data.0');
                $this->assertEquals(250, $list['profit_margin']['profit']);
                $this->assertEquals(1, $list['profit_margin']['lines_without_cost']);

                // Not asked for → not there.
                $plain = $this->getJson($url("/api/v1/tenant/sales/{$sale['id']}"))->assertOk()->json('data.sale');
                $this->assertArrayNotHasKey('profit_margin', $plain);
                $this->assertArrayNotHasKey('unit_cost', $plain['items'][0]);

                // Cashiers never see cost or profit, however they ask.
                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $cashier = $this->getJson($url("/api/v1/tenant/sales/{$sale['id']}?include_profit=1&include_cost=1"))->assertOk()->json('data.sale');
                $this->assertArrayNotHasKey('profit_margin', $cashier);
                foreach (['unit_cost', 'profit', 'cost_known'] as $key) {
                    $this->assertArrayNotHasKey($key, $cashier['items'][0]);
                }
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
