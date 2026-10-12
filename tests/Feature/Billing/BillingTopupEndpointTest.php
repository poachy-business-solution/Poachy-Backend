<?php

namespace Tests\Feature\Billing;

use App\Models\BillingTopup;
use App\Models\Tenant;
use Database\Seeders\BillingPriceSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * POST /api/v1/tenant/billing/topups/stk and GET /topups/{id} through the real tenant host,
 * Sanctum and role checks.
 */
class BillingTopupEndpointTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_only_the_owner_can_start_and_see_top_ups(): void
    {
        $this->configureTenantHttpDatabases();
        $this->seed(BillingPriceSeeder::class);
        Mail::fake();
        $domain = 'billing-topup-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $other = BillingTopup::query()->create(['tenant_id' => $this->createTenantHttpFixture('billing-other-'.strtolower(uniqid()).'.poachy.test')->id, 'amount' => 50, 'phone' => '254711111111', 'status' => 'completed']);
        $url = fn (string $path) => $this->tenantUrl($domain, $path);

        try {
            $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700])->assertUnauthorized();

            $tenant->run(function () use ($url, $other) {
                $cashier = $this->createTenantUserWithRole('cashier');
                $owner = $this->createTenantUserWithRole('owner');

                $this->actingAsTenant($cashier);
                $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700], ['Idempotency-Key' => 'k1'])->assertForbidden();

                $this->actingAsTenant($owner);
                $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
                $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 9], ['Idempotency-Key' => 'k1'])->assertUnprocessable()->assertJsonValidationErrors('amount');
                $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700, 'phone' => '12'], ['Idempotency-Key' => 'k1'])->assertUnprocessable()->assertJsonValidationErrors('phone');

                $created = $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700], ['Idempotency-Key' => 'k1'])
                    ->assertCreated()->assertJsonPath('data.status', 'processing')->assertJsonPath('data.amount', 700)->assertJsonPath('data.phone', '2547****0000');
                $id = $created->json('data.id');

                $this->postJson($url('/api/v1/tenant/billing/topups/stk'), ['amount' => 700], ['Idempotency-Key' => 'k1'])
                    ->assertOk()->assertJsonPath('data.id', $id);

                $this->getJson($url("/api/v1/tenant/billing/topups/{$id}"))->assertOk()->assertJsonPath('data.status', 'processing');
                $this->getJson($url("/api/v1/tenant/billing/topups/{$other->id}"))->assertNotFound();

                $this->actingAsTenant($cashier);
                $this->getJson($url("/api/v1/tenant/billing/topups/{$id}"))->assertForbidden();
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
            $this->deleteTenantHttpFixture((string) Tenant::query()->whereKey($other->tenant_id)->first()?->domains()->value('domain'));
        }
    }
}
