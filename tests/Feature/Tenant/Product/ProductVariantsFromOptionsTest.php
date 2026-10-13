<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Inventory;
use App\Models\Tenant\Product;
use App\Services\Tenant\Reports\StockReportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** F6: colours and sizes — options become variants in one request, each with its own stock, price, cost and alert level. */
class ProductVariantsFromOptionsTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_options_become_variants_with_their_own_stock_receiving_and_profit(): void
    {
        $this->configureTenantHttpDatabases();
        Storage::fake('public');
        $domain = 'var-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Shoes', 'slug' => 'shoes', 'created_at' => now(), 'updated_at' => now()]);
                $pair = DB::table('units_of_measure')->insertGetId(['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);
                $store = DB::table('stores')->insertGetId(['name' => 'Main branch', 'code' => 'MAIN', 'address' => 'Town', 'created_at' => now(), 'updated_at' => now()]);

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $uuid = $this->post($url('/api/v1/tenant/products'), ['name' => 'Block heels', 'category_id' => $cat, 'product_type' => 'simple', 'base_selling_price' => 2500,
                    'base_uom_id' => $pair, 'cost_price' => 1600, 'reorder_level' => 2, 'primary_image' => UploadedFile::fake()->image('p.jpg')], ['Accept' => 'application/json'])
                    ->assertCreated()->json('data.uuid');
                $product = Product::query()->where('uuid', $uuid)->firstOrFail();

                $body = ['options' => [['name' => 'Colour', 'values' => ['Black', 'Red']], ['name' => 'Size', 'values' => ['38', '39', '40']]],
                    'store_id' => $store, 'opening_stock' => 2,
                    'variants' => [['attributes' => ['Colour' => 'Red', 'Size' => '39'], 'opening_stock' => 5, 'variant_price' => 2600],
                        ['attributes' => ['Colour' => 'Red', 'Size' => '40'], 'opening_stock' => 0]]];
                $variants = $this->postJson($url("/api/v1/tenant/products/{$uuid}/variants/bulk"), $body)->assertCreated()->json('data');

                $this->assertSame(['Black / 38', 'Black / 39', 'Black / 40', 'Red / 38', 'Red / 39', 'Red / 40'], array_column($variants, 'variant_name'));
                $this->assertSame('variable', $product->fresh()->product_type->value);
                $this->assertEqualsCanonicalizing(['Colour' => 'Red', 'Size' => '39'], $variants[4]['attributes']);
                $this->assertEquals([2600, 1600, 2], [$variants[4]['variant_price'], $variants[4]['cost_price'], $variants[4]['reorder_level']]);
                $this->assertNull($variants[0]['variant_price']);

                $onHand = fn (int $variantId) => Inventory::query()->where('store_id', $store)->where('product_variant_id', $variantId)->first();
                $this->assertSame(['2.0000', '1600.0000'], [$onHand($variants[0]['id'])->quantity_on_hand, $onHand($variants[0]['id'])->average_cost]);
                $this->assertSame('5.0000', $onHand($variants[4]['id'])->quantity_on_hand);
                // No stock yet still gets a stock line, so the colour/size is listed as "out" and can be received.
                $this->assertSame(['0.0000', null], [$onHand($variants[5]['id'])->quantity_on_hand, $onHand($variants[5]['id'])->average_cost]);

                // Repeating the call adds nothing and changes nothing.
                $again = $this->postJson($url("/api/v1/tenant/products/{$uuid}/variants/bulk"), $body)->assertCreated()->json('data');
                $this->assertCount(6, $again);
                $this->assertSame('2.0000', $onHand($variants[0]['id'])->quantity_on_hand);

                // Stock list: one row per colour/size with its own level and alert.
                $report = app(StockReportService::class)->stockHealth($store);
                $rows = collect($report['items'])->keyBy('variant_id');
                $this->assertCount(6, $rows);
                $this->assertSame('Block heels — Red / 39', $rows[$variants[4]['id']]['name']);
                $this->assertEquals([5, 2600, 1600, 'new'], [$rows[$variants[4]['id']]['on_hand'], $rows[$variants[4]['id']]['selling_price'], $rows[$variants[4]['id']]['unit_cost'], $rows[$variants[4]['id']]['status']], 'in stock, arrived today, never sold → new (not "not selling")');
                $this->assertSame('low', $rows[$variants[0]['id']]['status']);
                $this->assertSame('out', $rows[$variants[5]['id']]['status']);

                // Receive one colour/size at a new price: only its average moves.
                $red38 = $variants[3]['id'];
                $this->postJson($url("/api/v1/tenant/products/{$uuid}/receive-stock"), ['store_id' => $store, 'variant_id' => $red38, 'quantity' => 10, 'unit_cost' => 1500])->assertCreated();
                $this->assertSame(['12.0000', '1516.6667'], [$onHand($red38)->quantity_on_hand, $onHand($red38)->average_cost]);
                $this->assertSame('1600.0000', $onHand($variants[0]['id'])->average_cost);
                $this->postJson($url("/api/v1/tenant/products/{$uuid}/receive-stock"), ['store_id' => $store, 'variant_id' => 999999, 'quantity' => 1])->assertUnprocessable();

                // Selling that colour/size is costed at its own average, and the cost history is per variant.
                Bus::fake();
                $sale = $this->postJson($url('/api/v1/tenant/sales?include_profit=1'), ['store_id' => $store,
                    'items' => [['product_id' => $product->id, 'variant_id' => $red38, 'uom_id' => $pair, 'quantity' => 1]],
                    'payments' => [['method' => 'cash', 'amount' => 2500]]])->assertCreated()->json('data.sale');
                $this->assertEquals(1516.67, $sale['items'][0]['unit_cost']);
                $this->assertSame('11.0000', $onHand($red38)->quantity_on_hand);
                $summary = $this->getJson($url("/api/v1/tenant/products/{$uuid}?variant_id={$red38}"))->assertOk()->json('data.cost_summary');
                $this->assertEquals([1516.67, [1500, 1600]], [$summary['average_cost'], array_column($summary['recent'], 'unit_cost')]);

                // The product list says the product has colours/sizes.
                $list = $this->getJson($url('/api/v1/tenant/products?search=heels'))->assertOk()->json('data');
                $this->assertSame('variable', collect($list['products'] ?? $list['data'] ?? [])->firstWhere('uuid', $uuid)['product_type']);

                // Cashiers cannot create variants.
                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $this->postJson($url("/api/v1/tenant/products/{$uuid}/variants/bulk"), $body)->assertForbidden();
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
