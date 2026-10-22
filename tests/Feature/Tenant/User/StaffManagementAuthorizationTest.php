<?php

namespace Tests\Feature\Tenant\User;

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/**
 * A manager runs the floor but can't take over the shop: they add and edit cashiers (and
 * themselves), never create an owner or manager, and never edit, move or remove one.
 */
class StaffManagementAuthorizationTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_a_manager_cannot_create_or_take_over_an_owner(): void
    {
        $this->configureTenantHttpDatabases();
        Mail::fake();
        $domain = 'staff-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $owner = $this->createTenantUserWithRole('owner');
                $otherManager = $this->createTenantUserWithRole('manager');
                $cashier = $this->createTenantUserWithRole('cashier');
                $manager = $this->createTenantUserWithRole('manager');
                $this->actingAsTenant($manager);
                $new = fn (string $role) => ['name' => 'New '.$role, 'email' => $role.'-'.uniqid().'@example.com', 'role' => $role];

                // Managers add cashiers only.
                $this->postJson($url('/api/v1/tenant/users'), $new('owner'))->assertStatus(422)->assertJsonPath('errors.role.0', 'Managers can add cashiers only. Ask the owner to add an owner or manager.');
                $this->postJson($url('/api/v1/tenant/users'), $new('manager'))->assertStatus(422);
                $this->postJson($url('/api/v1/tenant/users'), $new('cashier'))->assertCreated();

                // Not the owner's (or another manager's) email, status, branches or account.
                $this->putJson($url("/api/v1/tenant/users/{$owner->id}"), ['email' => 'mine@example.com'])->assertForbidden();
                $this->putJson($url("/api/v1/tenant/users/{$owner->id}"), ['is_active' => false])->assertForbidden();
                $this->putJson($url("/api/v1/tenant/users/{$otherManager->id}"), ['name' => 'Renamed'])->assertForbidden();
                $this->putJson($url("/api/v1/tenant/users/{$owner->id}/stores"), ['store_ids' => []])->assertForbidden();
                $this->deleteJson($url("/api/v1/tenant/users/{$owner->id}"))->assertForbidden();
                $this->assertTrue(User::query()->whereKey($owner->id)->where('is_active', true)->exists());

                // Cashiers and themselves are fine.
                $this->putJson($url("/api/v1/tenant/users/{$cashier->id}"), ['name' => 'Cashier Two'])->assertOk();
                $this->putJson($url("/api/v1/tenant/users/{$manager->id}"), ['phone' => '0711000000'])->assertOk();

                // The owner still manages everyone.
                $this->actingAsTenant($owner);
                $this->postJson($url('/api/v1/tenant/users'), $new('manager'))->assertCreated();
                $this->putJson($url("/api/v1/tenant/users/{$otherManager->id}"), ['name' => 'Renamed'])->assertOk();
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
