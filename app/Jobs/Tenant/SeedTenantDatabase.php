<?php

namespace App\Jobs\Tenant;

use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Stancl\Tenancy\Contracts\TenantWithDatabase;

class SeedTenantDatabase
{
    public function __construct(protected TenantWithDatabase $tenant) {}

    public function handle(): void
    {
        $this->tenant->run(function () {
            Artisan::call('db:seed', [
                '--class' => TenantDatabaseSeeder::class,
                '--force' => true,
            ]);
        });
    }
}
