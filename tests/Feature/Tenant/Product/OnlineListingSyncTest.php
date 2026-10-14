<?php

namespace Tests\Feature\Tenant\Product;

use App\Jobs\Tenant\ProcessOutboundProductSync;
use App\Models\BusinessDetail;
use App\Models\Tenant\SyncQueueOutbound;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * Item 28: a photo uploaded from the POS reaches the marketplace as a URL that works from the
 * central host, the online switch queues the sync, and stuck sync entries are re-queued.
 */
class OnlineListingSyncTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_switching_a_product_online_syncs_it_with_public_photo_urls(): void
    {
        $this->configureTenantHttpDatabases();
        $domain = 'online-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);
        Http::fake(['*/api/v1/central/sync/inbound/product' => Http::response(['success' => true, 'data' => ['sync_id' => 77]])]);

        try {
            $tenant->run(function () use ($url, $tenant) {
                $cat = DB::table('product_categories')->insertGetId(['name' => 'Shoes', 'slug' => 'shoes', 'created_at' => now(), 'updated_at' => now()]);
                $pair = DB::table('units_of_measure')->insertGetId(['code' => 'pair', 'name' => 'Pair', 'type' => 'count', 'is_base_unit' => true, 'created_at' => now(), 'updated_at' => now()]);

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $uuid = $this->post($url('/api/v1/tenant/products'), ['name' => 'Block heels', 'category_id' => $cat, 'product_type' => 'simple', 'base_selling_price' => 2500,
                    'base_uom_id' => $pair, 'primary_image' => UploadedFile::fake()->image('heels.png', 40, 40)], ['Accept' => 'application/json'])->assertCreated()->json('data.uuid');
                $this->assertSame(0, SyncQueueOutbound::query()->count(), 'nothing syncs while the product is offline');

                $this->patchJson($url("/api/v1/tenant/products/{$uuid}/online"), ['is_available_online' => true, 'online_price' => 2600])->assertOk()
                    ->assertJsonPath('data.is_available_online', true);

                $entry = SyncQueueOutbound::query()->where('syncable_type', 'Product')->latest('id')->firstOrFail();
                $prefix = 'http://localhost/api/v1/public/assets/'.$tenant->id.'/products/';
                $this->assertStringStartsWith($prefix, $entry->payload['primary_image']);
                $this->assertSame('completed', $entry->status);

                Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/sync/inbound/product')
                    && $r->hasHeader('Authorization')
                    && str_starts_with($r['payload']['primary_image'], $prefix)
                    && $r['payload']['online_price'] == 2600);

                // The photo is reachable from the central host, for the marketplace and share previews.
                $path = substr($entry->payload['primary_image'], strlen('http://localhost'));
                $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'max-age=86400, public');
                $this->get(str_replace('/products/', '/../', $path))->assertNotFound();
                $this->get('/api/v1/public/assets/no-such-tenant/products/x.png')->assertNotFound();
                $this->get('/api/v1/public/assets/'.$tenant->id.'/.env')->assertNotFound();

                // Share links (the fixture shop is verified) and photo removal by the URL the app holds.
                $shopSlug = BusinessDetail::on('central')->where('tenant_id', $tenant->id)->value('slug');
                $show = $this->getJson($url("/api/v1/tenant/products/{$uuid}"))->assertOk();
                $this->assertStringEndsWith("/s/{$shopSlug}/p/".$show->json('data.slug'), $show->json('data.share_url'));
                $this->assertStringEndsWith("/s/{$shopSlug}", $show->json('data.shop_url'));
                $this->post($url("/api/v1/tenant/products/{$uuid}/images"), ['images' => [UploadedFile::fake()->image('side.png', 30, 30)]], ['Accept' => 'application/json'])->assertOk();
                $side = $this->getJson($url("/api/v1/tenant/products/{$uuid}"))->json('data.secondary_images.0');
                $this->assertStringContainsString('/products/', $side);
                $this->deleteJson($url("/api/v1/tenant/products/{$uuid}/images"), ['images' => [$side]])->assertOk()->assertJsonPath('data.deleted_count', 1);
            });
        } finally {
            $tenant->run(fn () => Storage::disk('public')->deleteDirectory('products'));
            $this->deleteTenantHttpFixture($domain);
        }
    }

    public function test_failed_and_stuck_outbound_entries_are_requeued(): void
    {
        $this->configureTenantHttpDatabases();
        $domain = 'retry-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);

        try {
            $tenant->run(function () use ($tenant) {
                $row = fn (array $o) => SyncQueueOutbound::query()->forceCreate($o + ['tenant_id' => $tenant->id, 'syncable_type' => 'Product', 'syncable_id' => 1, 'action' => 'create',
                    'payload' => ['name' => 'x'], 'priority' => 3, 'scheduled_at' => now(), 'expires_at' => now()->addDay(), 'status' => 'failed', 'retry_count' => 1, 'max_retries' => 3,
                    'backoff_strategy' => 'exponential', 'idempotency_key' => uniqid('k'), 'payload_hash' => uniqid('h'), 'next_retry_at' => null]);
                $failed = $row(['next_retry_at' => now()->subMinute()]);
                $notYet = $row(['next_retry_at' => now()->addHour()]);
                $exhausted = $row(['retry_count' => 3, 'next_retry_at' => now()->subMinute()]);
                $stuck = $row(['status' => 'pending', 'retry_count' => 0]);
                DB::table('sync_queue_outbound')->where('id', $stuck->id)->update(['updated_at' => now()->subMinutes(30)]);
                $fresh = $row(['status' => 'pending', 'retry_count' => 0]);

                Queue::fake();
                Artisan::call('sync:retry-tenant-outbound', ['--tenant' => $tenant->id]);
                $this->assertStringContainsString('Re-queued 2 ', Artisan::output());

                Queue::assertPushed(ProcessOutboundProductSync::class, 2);
                $this->assertSame(['queued', 'failed', 'failed', 'queued', 'pending'],
                    [$failed->fresh()->status, $notYet->fresh()->status, $exhausted->fresh()->status, $stuck->fresh()->status, $fresh->fresh()->status]);
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
