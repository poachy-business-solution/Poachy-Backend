<?php

namespace Tests\Feature\Central\Concerns;

use App\Models\MarketplaceCustomer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

trait InteractsWithCentralHttpAuth
{
    /**
     * @var array<int>
     */
    private array $centralHttpUserIds = [];

    /**
     * @var array<int>
     */
    private array $centralHttpCustomerIds = [];

    protected function createCentralUserWithRole(string $roleName): User
    {
        config(['permission.connection' => 'central']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate($roleName, 'central');

        $user = User::on('central')->create([
            'name' => 'Central HTTP '.ucfirst($roleName),
            'email' => sprintf('central-http-%s-%s@example.com', $roleName, uniqid()),
            'password' => Hash::make('password'),
            'user_type' => $roleName === 'customer' ? 'customer' : 'admin',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($roleName);
        $this->centralHttpUserIds[] = $user->id;

        return $user;
    }

    protected function createCentralCustomerProfile(User $user): MarketplaceCustomer
    {
        $customer = MarketplaceCustomer::on('central')->create([
            'user_id' => $user->id,
            'customer_number' => MarketplaceCustomer::generateCustomerNumber(),
            'phone' => '+254700000000',
            'is_active' => true,
            'phone_verified' => true,
            'phone_verified_at' => now(),
            'accepts_marketing' => true,
            'accepts_sms' => true,
        ]);

        $this->centralHttpCustomerIds[] = $customer->id;

        return $customer;
    }

    protected function actingAsCentral(User $user): void
    {
        Sanctum::actingAs($user, ['*'], 'central');
    }

    protected function cleanupCentralHttpUsers(): void
    {
        if ($this->centralHttpUserIds === [] && $this->centralHttpCustomerIds === []) {
            return;
        }

        MarketplaceCustomer::on('central')
            ->whereIn('id', $this->centralHttpCustomerIds)
            ->forceDelete();

        DB::connection('central')
            ->table(config('permission.table_names.model_has_roles'))
            ->where('model_type', User::class)
            ->whereIn('model_id', $this->centralHttpUserIds)
            ->delete();

        User::on('central')
            ->whereIn('id', $this->centralHttpUserIds)
            ->forceDelete();

        $this->centralHttpUserIds = [];
        $this->centralHttpCustomerIds = [];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
