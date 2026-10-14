<?php

namespace Tests\Feature\Public;

use App\Models\AdminAuditLog;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceItem;
use App\Models\MarketplaceProduct;
use App\Services\Central\Marketplace\MarketplaceItemService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Item 30: the public marketplace API. Listings of verified shops only; the same barcode
 * across shops becomes one product page with every shop's offer; admin can map by hand.
 */
class PublicMarketTest extends TestCase
{
    private string $run;

    /** @var array<string, string> */
    private array $tenants = [];

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
        Config::set('internal_admin.token', 'internal-test-token-0123456789abcdef0123456789');
        Config::set('internal_admin.allowed_ips', ['127.0.0.1', '10.0.0.0/8']);
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Cache::flush();

        $this->run = strtolower(uniqid());
        $this->category = MarketplaceCategory::on('central')->create(['name' => 'Juices '.$this->run, 'slug' => 'juices-'.$this->run, 'is_active' => true]);
        foreach (['nairobi' => true, 'nakuru' => true, 'pending' => false] as $key => $verified) {
            $id = "market-{$key}-{$this->run}";
            $this->tenants[$key] = $id;
            DB::connection('central')->table('tenants')->insert(['id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            DB::connection('central')->table('domains')->insert(['domain' => "{$id}.poachy.test", 'tenant_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            BusinessDetail::on('central')->create(['tenant_id' => $id, 'business_name' => ucfirst($key).' Duka '.$this->run, 'business_phone' => '+254700000'.random_int(100, 999),
                'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
                'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
                'city' => ucfirst($key), 'county' => ucfirst($key), 'status' => 'active', 'is_verified' => $verified, 'verified_at' => $verified ? now() : null, 'rating' => $key === 'nakuru' ? 4.5 : 3.0]);
        }
    }

    protected function tearDown(): void
    {
        $ids = array_values($this->tenants);
        MarketplaceProduct::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        MarketplaceItem::on('central')->where('slug', 'like', '%'.$this->run.'%')->orWhere('barcode', 'like', '%'.$this->run)->delete();
        BusinessDetail::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        DB::connection('central')->table('domains')->whereIn('tenant_id', $ids)->delete();
        DB::connection('central')->table('tenants')->whereIn('id', $ids)->delete();
        AdminAuditLog::on('central')->whereIn('tenant_id', $ids)->delete();
        $this->category->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function listing(string $tenantKey, string $name, float $price, ?string $barcode, array $extra = []): MarketplaceProduct
    {
        $product = MarketplaceProduct::on('central')->create($extra + [
            'tenant_id' => $this->tenants[$tenantKey], 'name' => $name, 'slug' => Str::slug($name).'-'.strtolower(uniqid()), 'sku' => 'SKU-'.uniqid(),
            'barcode' => $barcode, 'marketplace_category_id' => $this->category->id, 'online_price' => $price, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece',
            'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true, 'primary_image' => "http://localhost/img/{$name}.png",
        ]);
        app(MarketplaceItemService::class)->attachByBarcode($product); // what every sync does

        return $product;
    }

    public function test_same_barcode_across_verified_shops_is_one_product_page_with_offers(): void
    {
        $barcode = '61610'.$this->run;
        $nairobi = $this->listing('nairobi', 'Mango Juice 1L', 180, $barcode);
        $nakuru = $this->listing('nakuru', 'Mango juice 1 litre', 170, $barcode);
        $this->listing('pending', 'Mango Juice 1L', 150, $barcode); // not verified → never shown
        $loose = $this->listing('nairobi', 'Passion Juice 1L', 190, null);
        $this->assertSame($nairobi->fresh()->marketplace_item_id, $nakuru->fresh()->marketplace_item_id);

        $page = $this->getJson("/api/v1/public/market/products/{$nairobi->slug}")->assertOk()->json('data');
        $this->assertSame('Mango Juice 1L', $page['name']);
        $this->assertSame(2, $page['offers_count']);
        $this->assertEquals(170, $page['from_price']);
        $this->assertEquals([170, 180], array_column($page['offers'], 'price'));
        $this->assertSame('Nakuru Duka '.$this->run, $page['offers'][0]['shop']['name']);
        $this->assertTrue($page['offers'][0]['shop']['is_verified']);
        $this->assertSame('No returns', $page['offers'][0]['shop']['return_policy']);
        $this->assertFalse($page['offers'][0]['delivery']);
        $this->assertSame('Passion Juice 1L', $this->getJson("/api/v1/public/market/products/{$loose->slug}")->assertOk()->json('data.name'));
        $this->assertSame(1, $this->getJson("/api/v1/public/market/products/{$loose->slug}")->json('data.offers_count'));
        $this->getJson("/api/v1/public/market/products/{$page['item_slug']}")->assertOk()->assertJsonPath('data.offers_count', 2); // item slug works too
        $this->getJson('/api/v1/public/market/products/no-such-thing')->assertNotFound();

        // Home, category and search list verified shops' goods only.
        $home = $this->getJson('/api/v1/public/market/home')->assertOk()->json('data');
        $cat = collect($home['categories'])->firstWhere('slug', $this->category->slug);
        $this->assertSame(3, $cat['products_count']);
        $this->assertSame(['Nakuru Duka '.$this->run, 'Nairobi Duka '.$this->run], collect($home['featured_shops'])->pluck('name')->filter(fn ($n) => str_contains($n, $this->run))->values()->all());
        $this->assertSame([], $home['sponsored']);

        $catPage = $this->getJson("/api/v1/public/market/categories/{$this->category->slug}?sort=price_asc")->assertOk()->json('data');
        $this->assertEquals([170, 180, 190], array_column($catPage['products'], 'price'));
        $this->assertSame(3, $catPage['pagination']['total']);
        $this->assertEquals([180, 190], array_column($this->getJson("/api/v1/public/market/categories/{$this->category->slug}?min_price=175&sort=price_asc")->json('data.products'), 'price'));
        $this->assertEquals([170], array_column($this->getJson("/api/v1/public/market/categories/{$this->category->slug}?county=Nakuru")->json('data.products'), 'price'));
        $this->getJson("/api/v1/public/market/categories/{$this->category->slug}?sort=sideways")->assertUnprocessable();
        $this->getJson('/api/v1/public/market/categories/nope-'.$this->run)->assertNotFound();

        $search = $this->getJson('/api/v1/public/market/search?q=mango+juice')->assertOk()->json('data');
        $this->assertSame(2, $search['pagination']['total']);
        $this->assertSame([], $search['shops'], 'no shop is called mango juice');
        $byTown = $this->getJson('/api/v1/public/market/search?q=Nakuru')->assertOk()->json('data');
        $this->assertSame(['Nakuru Duka '.$this->run], array_column($byTown['shops'], 'name'), 'shop search is by name or town');
        $this->assertSame(2, $this->getJson("/api/v1/public/market/search?q={$barcode}")->json('data.pagination.total'), 'search by barcode');
        $this->getJson('/api/v1/public/market/search?q=m')->assertUnprocessable();

        // Admin maps the loose listing into the item by hand; wrong role refused; audited.
        $headers = fn (string $role) => ['Authorization' => 'Bearer internal-test-token-0123456789abcdef0123456789', 'X-Admin-Id' => '42', 'X-Admin-Name' => 'Ops', 'X-Admin-Role' => $role, 'Accept' => 'application/json'];
        $this->postJson("/api/internal/admin/marketplace/products/{$loose->id}/item", ['item_id' => $nairobi->fresh()->marketplace_item_id], $headers('support'))->assertForbidden();
        $this->postJson("/api/internal/admin/marketplace/products/{$loose->id}/item", [], $headers('operations'))->assertUnprocessable();
        $this->postJson("/api/internal/admin/marketplace/products/{$loose->id}/item", ['item_id' => $nairobi->fresh()->marketplace_item_id, 'reason' => 'Same juice'], $headers('operations'))
            ->assertOk()->assertJsonPath('data.item.offers_count', 4); // admin counts the unverified shop's listing too
        $this->assertSame(3, $this->getJson("/api/v1/public/market/products/{$nairobi->slug}")->json('data.offers_count'));
        $this->assertSame('marketplace.product.map', AdminAuditLog::on('central')->where('tenant_id', $this->tenants['nairobi'])->latest('id')->value('action'));
        $this->deleteJson("/api/internal/admin/marketplace/products/{$loose->id}/item", ['reason' => 'Oops'], $headers('operations'))->assertOk();
        $this->assertSame(2, $this->getJson("/api/v1/public/market/products/{$nairobi->slug}")->json('data.offers_count'));

        // A listing with a brand-new name goes under a new item.
        $this->postJson("/api/internal/admin/marketplace/products/{$loose->id}/item", ['item_name' => 'Passion juice '.$this->run], $headers('super'))->assertOk()->assertJsonPath('data.item.offers_count', 1);
    }
}
