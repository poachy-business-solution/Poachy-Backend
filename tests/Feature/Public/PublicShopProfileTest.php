<?php

namespace Tests\Feature\Public;

use App\Models\BusinessDetail;
use App\Models\MarketplaceProduct;
use Illuminate\Support\Facades\Cache;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * Item 27: a shop's public page. The owner sets the link, GPS pin, WhatsApp and return
 * window; buyers read it at /public/shops/{slug}; nothing shows until the shop is verified.
 */
class PublicShopProfileTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_owner_edits_the_shop_page_and_the_public_profile_follows_the_verified_badge(): void
    {
        $this->configureTenantHttpDatabases();
        $domain = 'shop-page-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);
        $detail = BusinessDetail::on('central')->where('tenant_id', $tenant->id)->firstOrFail();
        $slug = 'duka-'.strtolower(uniqid());

        try {
            // A slug exists from creation and the page is reachable because the fixture shop is verified.
            $this->assertNotEmpty($detail->slug);
            $this->getJson("/api/v1/public/shops/{$detail->slug}")->assertOk()
                ->assertJsonPath('data.name', 'HTTP Smoke Tenant')->assertJsonPath('data.is_verified', true)
                ->assertJsonPath('data.return_policy', 'No returns')->assertJsonPath('data.whatsapp_link', null)
                ->assertJsonMissingPath('data.business_email');

            $tenant->run(function () use ($url, $slug, $tenant) {
                // Only the owner edits the page.
                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['slug' => $slug])->assertForbidden();

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $this->getJson($url('/api/v1/tenant/business-details/shop-page'))->assertOk()
                    ->assertJsonPath('data.is_listed', true)->assertJsonPath('data.return_window_days', 0);

                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['slug' => 'Bad Slug!'])->assertUnprocessable()->assertJsonValidationErrors('slug');
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['whatsapp_number' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('whatsapp_number');
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['latitude' => -1.28])->assertUnprocessable()->assertJsonValidationErrors('longitude');
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['return_window_days' => 5])->assertUnprocessable()->assertJsonValidationErrors('return_window_days');

                $r = $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), [
                    'slug' => $slug, 'whatsapp_number' => '0712 345 678', 'latitude' => -1.2833, 'longitude' => 36.8167, 'return_window_days' => 7,
                ])->assertOk();
                $this->assertSame($slug, $r->json('data.slug'));
                $this->assertSame('https://wa.me/254712345678', $r->json('data.whatsapp_link'));
                $this->assertSame('Returns within 7 days', $r->json('data.return_policy'));
                $this->assertStringEndsWith('/s/'.$slug, $r->json('data.public_url'));
                $this->assertSame($tenant->id, BusinessDetail::on('central')->where('slug', $slug)->value('tenant_id'));

                // The payout number: a Kenyan mobile, stored normalised; empty falls back to the business phone.
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['payout_phone' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('payout_phone');
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['payout_phone' => '0711 222 333'])->assertOk()->assertJsonPath('data.payout_phone', '254711222333');
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['payout_phone' => ''])->assertOk()->assertJsonPath('data.payout_phone', null);
                // Item 48: VAT is off until the owner switches it on.
                $this->getJson($url('/api/v1/tenant/business-details/shop-page'))->assertOk()->assertJsonPath('data.charge_vat', false);
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['charge_vat' => true])->assertOk()->assertJsonPath('data.charge_vat', true);
                $this->patchJson($url('/api/v1/tenant/business-details/shop-page'), ['charge_vat' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('charge_vat');

                // Another shop with the same name gets a numbered link, and nobody can claim this one.
                $this->assertSame($slug.'-2', BusinessDetail::uniqueSlug($slug));
            });

            // Buyers see the page with directions and WhatsApp; online product count comes from the marketplace sync.
            MarketplaceProduct::on('central')->create(['tenant_id' => $tenant->id, 'name' => 'Block heels', 'slug' => 'block-heels-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
                'online_price' => 2500, 'base_uom_code' => 'pair', 'base_uom_name' => 'Pair', 'tax_rate' => 0, 'available_quantity' => 3, 'stock_status' => 'in_stock', 'is_active' => true]);
            $this->getJson("/api/v1/public/shops/{$slug}")->assertOk()
                ->assertJsonPath('data.whatsapp_link', 'https://wa.me/254712345678')
                ->assertJsonPath('data.location.latitude', -1.2833)
                ->assertJsonPath('data.directions_url', 'https://www.google.com/maps/dir/?api=1&destination=-1.2833,36.8167')
                ->assertJsonPath('data.return_window_days', 7)
                ->assertJsonPath('data.online_products', 1);
            $this->getJson("/api/v1/public/shops/{$detail->slug}")->assertNotFound(); // the old link is gone
            $this->assertCount(1, $this->listedProducts($tenant->id));

            // Not verified → no page and no marketplace listing.
            $detail->fresh()->unverify();
            Cache::flush();
            $this->getJson("/api/v1/public/shops/{$slug}")->assertNotFound();
            $this->assertCount(0, $this->listedProducts($tenant->id));
        } finally {
            MarketplaceProduct::on('central')->where('tenant_id', $tenant->id)->forceDelete();
            $this->deleteTenantHttpFixture($domain);
        }
    }

    /** @return array<int, mixed> */
    private function listedProducts(string $tenantId): array
    {
        $json = $this->getJson("/api/v1/central/marketplace/products?tenant_id={$tenantId}")->assertOk()->json();

        return $json['data']['data'] ?? $json['data'] ?? [];
    }
}
