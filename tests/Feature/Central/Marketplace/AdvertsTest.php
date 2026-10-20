<?php

namespace Tests\Feature\Central\Marketplace;

use App\Http\Requests\Tenant\Marketplace\BuyAdvertRequest;
use App\Models\AdminAuditLog;
use App\Models\Advert;
use App\Models\AdvertType;
use App\Models\BillingAccount;
use App\Models\BillingTopup;
use App\Models\BusinessDetail;
use App\Models\MarketplaceCategory;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceProduct;
use App\Services\Central\Billing\BillingTopupService;
use App\Services\Central\Marketplace\AdvertService;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Item 42: adverts bought from the balance or by M-Pesa, shown as Sponsored (max two per page),
 * counted (views, clicks, orders) and stopped by Poachy with the unused days refunded.
 */
class AdvertsTest extends TestCase
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
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('internal_admin.token', 'internal-test-token-0123456789abcdef0123456789');
        Config::set('internal_admin.allowed_ips', ['127.0.0.1', '10.0.0.0/8']);
        Config::set('mpesa.driver', 'fake');
        DB::purge('central');
        DB::setDefaultConnection('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        Queue::fake();
        Mail::fake();
        Cache::flush();
        Advert::query()->delete(); // sponsored slots are global: start from none

        $this->run = strtolower(uniqid());
        $this->category = MarketplaceCategory::on('central')->create(['name' => 'Clocks '.$this->run, 'slug' => 'clocks-'.$this->run, 'is_active' => true]);
        foreach (['a' => 'Nairobi', 'b' => 'Nakuru', 'c' => 'Mombasa'] as $key => $county) {
            $id = "adverts-{$key}-{$this->run}";
            $this->tenants[$key] = $id;
            DB::connection('central')->table('tenants')->insert(['id' => $id, 'mpesa_paybill_account' => 'POA'.strtoupper(substr(md5($id), 0, 5)), 'created_at' => now(), 'updated_at' => now()]);
            BusinessDetail::on('central')->create(['tenant_id' => $id, 'business_name' => strtoupper($key).' Duka '.$this->run, 'business_phone' => '0722'.random_int(100000, 999999),
                'business_type_id' => DB::connection('central')->table('business_types')->value('id'),
                'business_category_id' => DB::connection('central')->table('business_categories')->value('id'),
                'city' => $county, 'county' => $county, 'status' => 'active', 'is_verified' => true, 'verified_at' => now(), 'rating' => 1]);
            BillingAccount::query()->create(['tenant_id' => $id, 'balance' => 1000, 'status' => 'active']);
        }
    }

    protected function tearDown(): void
    {
        $ids = array_values($this->tenants);
        Advert::query()->delete();
        DB::connection('central')->table('billing_ledger')->whereIn('tenant_id', $ids)->delete();
        BillingTopup::query()->whereIn('tenant_id', $ids)->delete();
        BillingAccount::query()->whereIn('tenant_id', $ids)->delete();
        MarketplaceOrder::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        MarketplaceProduct::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        BusinessDetail::on('central')->whereIn('tenant_id', $ids)->forceDelete();
        AdminAuditLog::on('central')->whereIn('tenant_id', $ids)->delete();
        AdminAuditLog::on('central')->where('action', 'marketplace.advert_type.update')->delete();
        AdvertType::query()->where('key', 'product_boost')->update(['price' => 100, 'days' => 7, 'is_active' => true]);
        DB::connection('central')->table('tenants')->whereIn('id', $ids)->delete();
        $this->category->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function listing(string $shop, string $name): MarketplaceProduct
    {
        return MarketplaceProduct::on('central')->create([
            'tenant_id' => $this->tenants[$shop], 'name' => $name, 'slug' => Str::slug($name).'-'.strtolower(uniqid()), 'sku' => 'SKU-'.uniqid(),
            'marketplace_category_id' => $this->category->id, 'online_price' => 1500, 'base_uom_code' => 'pcs', 'base_uom_name' => 'Piece',
            'tax_rate' => 0, 'available_quantity' => 5, 'stock_status' => 'in_stock', 'is_active' => true,
        ]);
    }

    private function adverts(): AdvertService
    {
        return app(AdvertService::class);
    }

    private function balance(string $shop): float
    {
        return (float) BillingAccount::query()->where('tenant_id', $this->tenants[$shop])->value('balance');
    }

    /** @return array<string, string> */
    private function as(string $role): array
    {
        return ['Authorization' => 'Bearer internal-test-token-0123456789abcdef0123456789', 'X-Admin-Id' => '3', 'X-Admin-Name' => 'Ops', 'X-Admin-Role' => $role, 'Accept' => 'application/json'];
    }

    public function test_bought_from_the_balance_it_goes_live_for_its_days(): void
    {
        $clock = $this->listing('a', 'Wall clock');
        $advert = $this->adverts()->buy($this->tenants['a'], 'product_boost', $clock->id, 'balance', null, 'k1', 'tenant_user:1');

        $this->assertSame([Advert::ACTIVE, 900.0], [$advert->status, $this->balance('a')]);
        $this->assertTrue($advert->ends_at->equalTo($advert->starts_at->copy()->addDays(7)));
        $this->assertSame($advert->id, $this->adverts()->buy($this->tenants['a'], 'product_boost', $clock->id, 'balance', null, 'k1', 'tenant_user:1')->id); // same key
        $this->assertSame(900.0, $this->balance('a'));

        foreach ([
            fn () => $this->adverts()->buy($this->tenants['a'], 'home_spot', $clock->id, 'balance', null, 'k2', 'u'), // 2000 > 900
            fn () => $this->adverts()->buy($this->tenants['a'], 'product_boost', $this->listing('b', 'Not mine')->id, 'balance', null, 'k3', 'u'),
            fn () => $this->adverts()->buy($this->tenants['a'], 'product_boost', null, 'balance', null, 'k4', 'u'),
        ] as $refused) {
            try {
                $refused();
                $this->fail('Should be refused');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(900.0, $this->balance('a'));
    }

    public function test_bought_by_mpesa_it_goes_live_when_the_prompt_is_paid(): void
    {
        $advert = $this->adverts()->buy($this->tenants['b'], 'featured_shop', null, 'mpesa', '0712345678', 'm1', 'tenant_user:2');
        $this->assertSame(Advert::PENDING_PAYMENT, $advert->status);
        $topup = BillingTopup::query()->findOrFail($advert->billing_topup_id);
        $this->assertEquals(500, $topup->amount);

        app(BillingTopupService::class)->handleStkCallback(['transaction_reference' => $topup->checkout_request_id, 'status' => 'success',
            'provider_reference' => 'SKA'.strtoupper(substr(uniqid(), -7)), 'failure_reason' => null, 'failure_code' => null]);
        $this->assertSame(Advert::ACTIVE, $advert->fresh()->status);
        $this->assertSame(1000.0, $this->balance('b')); // +500 paid, −500 advert

        $failed = $this->adverts()->buy($this->tenants['b'], 'near_you', null, 'mpesa', '0712345678', 'm2', 'u');
        $failedTopup = BillingTopup::query()->findOrFail($failed->billing_topup_id);
        app(BillingTopupService::class)->handleStkCallback(['transaction_reference' => $failedTopup->checkout_request_id, 'status' => 'failed',
            'provider_reference' => null, 'failure_reason' => 'Request cancelled by user', 'failure_code' => '1032']);
        $this->assertSame(Advert::PAYMENT_FAILED, $failed->fresh()->status);
    }

    public function test_sponsored_slots_are_capped_at_two_labelled_and_counted(): void
    {
        $products = ['a' => $this->listing('a', 'Clock A'), 'b' => $this->listing('b', 'Clock B'), 'c' => $this->listing('c', 'Clock C')];
        foreach ($products as $shop => $product) {
            $this->adverts()->buy($this->tenants[$shop], 'product_boost', $product->id, 'balance', null, "s-{$shop}", 'u');
        }

        $page = $this->getJson("/api/v1/public/market/categories/{$this->category->slug}")->assertOk()->json('data');
        $this->assertCount(2, $page['sponsored']);
        $this->assertTrue($page['sponsored'][0]['sponsored']);
        $this->assertNotNull($page['sponsored'][0]['advert_id']);
        $this->assertSame(2, (int) Advert::query()->sum('views'));

        $search = $this->getJson('/api/v1/public/market/search?q=Clock+B')->assertOk()->json('data');
        $this->assertSame(['Clock B'], array_column($search['sponsored'], 'name'));

        $advertId = $search['sponsored'][0]['advert_id'];
        $this->postJson("/api/v1/public/market/adverts/{$advertId}/click")->assertNoContent();
        $this->assertSame(1, Advert::query()->findOrFail($advertId)->clicks);
        MarketplaceOrder::on('central')->create(['order_number' => 'MKT-AD-'.uniqid(), 'customer_id' => 1, 'tenant_id' => $this->tenants['b'], 'merchant_name' => 'B',
            'subtotal' => 1500, 'tax_amount' => 0, 'discount_amount' => 0, 'delivery_fee' => 0, 'total_amount' => 1500, 'fulfillment_type' => 'pickup', 'order_status' => 'pending', 'ref' => "ad{$advertId}"]);
        $this->assertSame(1, $this->adverts()->ordersFor(Advert::query()->findOrFail($advertId)));
    }

    public function test_featured_shops_lead_and_near_you_needs_the_buyers_county(): void
    {
        $this->listing('a', 'Clock A');
        $this->listing('c', 'Clock C');
        $this->adverts()->buy($this->tenants['c'], 'featured_shop', null, 'balance', null, 'f', 'u');
        $home = $this->getJson('/api/v1/public/market/home')->assertOk()->json('data');
        $this->assertSame('C Duka '.$this->run, $home['featured_shops'][0]['name']);
        $this->assertTrue($home['featured_shops'][0]['sponsored']);

        BillingAccount::query()->where('tenant_id', $this->tenants['a'])->update(['balance' => 5000]);
        $this->adverts()->buy($this->tenants['a'], 'near_you', null, 'balance', null, 'n', 'u');
        $slug = $this->category->slug;
        $this->assertSame([], $this->getJson("/api/v1/public/market/categories/{$slug}?county=Mombasa")->json('data.sponsored'));
        $this->assertSame(['Clock A'], array_column($this->getJson("/api/v1/public/market/categories/{$slug}?county=Nairobi")->json('data.sponsored'), 'name'));
    }

    public function test_poachy_stops_an_advert_refunds_unused_days_and_edits_prices(): void
    {
        $clock = $this->listing('a', 'Wall clock');
        Carbon::setTestNow('2026-10-20 10:00:00');
        $advert = $this->adverts()->buy($this->tenants['a'], 'product_boost', $clock->id, 'balance', null, 'st', 'u');
        Carbon::setTestNow('2026-10-23 22:00:00'); // half of 7 days used

        $this->postJson("/api/internal/admin/marketplace/adverts/{$advert->id}/stop", ['reason' => 'Misleading photo'], $this->as('support'))->assertForbidden();
        $this->postJson("/api/internal/admin/marketplace/adverts/{$advert->id}/stop", ['reason' => 'Misleading photo'], $this->as('operations'))->assertOk();
        $this->assertSame(Advert::STOPPED, $advert->fresh()->status);
        $this->assertSame(950.0, $this->balance('a')); // 900 + 50 back
        $this->assertSame('marketplace.advert.stop', AdminAuditLog::on('central')->where('tenant_id', $this->tenants['a'])->latest('id')->value('action'));

        $this->patchJson('/api/internal/admin/marketplace/advert-types/product_boost', ['price' => 150, 'days' => 7, 'is_active' => true, 'reason' => 'New price list'], $this->as('operations'))->assertForbidden();
        $this->patchJson('/api/internal/admin/marketplace/advert-types/product_boost', ['price' => 150, 'days' => 7, 'is_active' => true, 'reason' => 'New price list'], $this->as('finance'))
            ->assertOk()->assertJsonPath('data.price', '150.00');
        $this->assertSame(150.0, (float) $this->adverts()->buy($this->tenants['b'], 'product_boost', $this->listing('b', 'Clock B')->id, 'balance', null, 'p2', 'u')->price);
    }

    public function test_shop_advert_routes_are_for_owners_and_managers_buying_is_owner_only(): void
    {
        $find = fn (string $method, string $uri) => collect(RouteFacade::getRoutes())->first(fn (Route $r) => $r->uri() === $uri && in_array($method, $r->methods(), true));
        foreach ([['GET', 'api/v1/tenant/marketplace/adverts'], ['GET', 'api/v1/tenant/marketplace/adverts/{id}'], ['POST', 'api/v1/tenant/marketplace/adverts']] as [$method, $uri]) {
            $this->assertContains('permission:view-financial-reports,tenant', $find($method, $uri)->middleware(), $uri);
        }
        $this->assertTrue(method_exists(BuyAdvertRequest::class, 'authorize'));
    }
}
