<?php

namespace App\Console\Commands;

use Database\Seeders\BusinessTypeSeeder;
use Database\Seeders\CentralRolesPermissionsSeeder;
use Database\Seeders\MarketplaceBrandSeeder;
use Database\Seeders\MarketplaceCategorySeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupCentralTestDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:setup-central-db
                            {--fresh : Drop and recreate the database before migrating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create/migrate/seed the isolated central database used by the test suite (see phpunit.xml CENTRAL_DB_DATABASE)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        $database = config('database.connections.central.database');

        if (! str_ends_with($database, '_test')) {
            $this->error("config('database.connections.central.database') resolved to \"{$database}\", which doesn't look like a test database (expected a _test suffix). Refusing to run — this command is only for the isolated database tests use.");

            return self::FAILURE;
        }

        $rootConnection = DB::connection('central')->getConfig();
        $rootConnection['database'] = '';
        config(['database.connections.central_root' => $rootConnection]);

        if ($this->option('fresh')) {
            $this->info("Dropping database `{$database}` if it exists...");
            DB::connection('central_root')->statement("DROP DATABASE IF EXISTS `{$database}`");
        }

        $this->info("Creating database `{$database}` if it doesn't exist...");
        DB::connection('central_root')->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $this->info('Running central migrations...');
        $this->call('migrate', ['--database' => 'central', '--force' => true]);

        $this->info('Seeding reference data...');
        foreach ([
            BusinessTypeSeeder::class,
            SubscriptionPlanSeeder::class,
            CentralRolesPermissionsSeeder::class,
            MarketplaceCategorySeeder::class,
            MarketplaceBrandSeeder::class,
        ] as $seeder) {
            $this->call('db:seed', ['--class' => $seeder, '--force' => true]);
        }

        $this->info("Done. `{$database}` is ready for the test suite.");

        return self::SUCCESS;
    }
}
