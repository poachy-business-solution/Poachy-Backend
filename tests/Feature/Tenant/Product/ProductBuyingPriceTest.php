<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Services\Tenant\Inventory\ProductCostService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** F3: buying price + low-stock alert level set when the product is created. */
class ProductBuyingPriceTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_buying_price_and_alert_level_at_creation(): void
    {
        $this->configureTenantHttpDatabases();
        Storage::fake('public');
        $domain = 'cost-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Shoes', 'slug' => 'shoes', 'created_at' => now(), 'updated_at' => now()]);
                $pair = DB::table('units_of_measure')->insertGetId(['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);
                $body = ['name' => 'Block heels black', 'category_id' => $cat, 'product_type' => 'simple', 'base_selling_price' => 2500,
                    'base_uom_id' => $pair, 'cost_price' => 1600, 'reorder_level' => 3, 'primary_image' => UploadedFile::fake()->image('p.jpg')];

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $this->postJson($url('/api/v1/tenant/products'), ['cost_price' => -5] + $body)->assertUnprocessable()->assertJsonValidationErrors('cost_price');
                $uuid = $this->post($url('/api/v1/tenant/products'), $body, ['Accept' => 'application/json'])->assertCreated()
                    ->assertJsonPath('data.cost_price', 1600)->json('data.uuid');

                $product = Product::query()->where('uuid', $uuid)->firstOrFail();
                $this->assertSame(['1600.0000', '3.0000'], [$product->cost_price, $product->reorder_level]);

                // With no deliveries yet, profit uses the entered buying price.
                $this->assertSame(1600.0, app(ProductCostService::class)->unitCost($product, 1));

                // Editable later.
                $this->patchJson($url("/api/v1/tenant/products/{$uuid}/inventory"), ['cost_price' => 1700, 'reorder_level' => 5])->assertOk();
                $this->assertSame(['1700.0000', '5.0000'], [$product->fresh()->cost_price, $product->fresh()->reorder_level]);

                // Cashiers never see buying prices.
                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $this->assertArrayNotHasKey('cost_price', $this->getJson($url("/api/v1/tenant/products/{$uuid}"))->assertOk()->json('data'));
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
