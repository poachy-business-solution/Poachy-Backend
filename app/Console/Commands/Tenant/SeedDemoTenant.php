<?php

namespace App\Console\Commands\Tenant;

use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use App\Models\Domain;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\Central\Admin\Tenant\TenantService;
use App\Services\Tenant\TenantAccessService;
use Database\Seeders\Demo\DemoStaffSeeder;
use Database\Seeders\Demo\DemoTenantDataSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitsOfMeasureSeeder;
use Database\Seeders\UomConversionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SeedDemoTenant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:seed-demo';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the fixed demo tenant with a full realistic dataset across every tenant table, for frontend development';

    public const DOMAIN = 'demo.poachy.test';

    /**
     * Execute the console command.
     */
    public function handle(TenantService $tenantService): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return self::FAILURE;
        }

        // Prevent queued listeners (credit-sale notices, budget alerts, expiry
        // notices, ...) and any mail from actually sending while we synthesize data.
        Mail::fake();
        Notification::fake();

        $existing = Domain::where('domain', self::DOMAIN)->first();

        if ($existing) {
            $this->warn("Existing demo tenant found ({$existing->tenant_id}) — deleting it (and its database) first...");
            $tenantService->deleteTenant($existing->tenant_id);
        }

        $this->info('Provisioning demo tenant at '.self::DOMAIN.'...');

        $tenant = $tenantService->createTenant([
            'domain' => self::DOMAIN,
            'tenant_name' => 'Poachy Demo Store',
            'notes' => 'Auto-generated full demo dataset for frontend development. Rebuilt via tenant:seed-demo — do not use for real business data.',
        ]);

        $this->info('Tenant database created and baseline-seeded. Adding central business profile...');

        $this->seedCentralBusinessProfile($tenant);

        $this->info('Seeding full demo dataset inside the tenant database (this can take a few minutes)...');

        $tenant->run(function () {
            foreach ([ProductCategorySeeder::class, UnitsOfMeasureSeeder::class, UomConversionsSeeder::class] as $seeder) {
                Artisan::call('db:seed', [
                    '--class' => $seeder,
                    '--force' => true,
                ]);

                $this->output->write(Artisan::output());
            }

            Artisan::call('db:seed', [
                '--class' => DemoTenantDataSeeder::class,
                '--force' => true,
            ]);

            $this->output->write(Artisan::output());
        });

        $this->newLine();
        $this->info('Demo tenant ready.');
        $this->printSummary();

        return self::SUCCESS;
    }

    /**
     * Create the central-side BusinessDetail + BusinessSubscription for the demo tenant,
     * mirroring what onboarding creates for a real merchant (see TenantSeeder for the
     * illustrative single-tenant equivalent this is modeled on).
     */
    protected function seedCentralBusinessProfile(Tenant $tenant): void
    {
        $category = BusinessCategory::where('slug', 'supermarket')->firstOrFail();
        $plan = SubscriptionPlan::where('slug', 'enterprise')->firstOrFail();

        BusinessDetail::create([
            'tenant_id' => $tenant->id,
            'business_name' => 'Poachy Demo Store',
            'business_description' => 'Full-catalogue demo store used to exercise every feature of the platform — electronics, groceries, and household goods under one roof.',
            'business_type_id' => $category->business_type_id,
            'business_category_id' => $category->id,
            'business_email' => 'demo@poachy.test',
            'business_phone' => '+254700000000',
            'contact_person' => 'Demo Owner',
            'address' => 'Demo Plaza, Moi Avenue',
            'city' => 'Nairobi',
            'county' => 'Nairobi',
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now()->subMonths(6),
            'onboarded_at' => now()->subMonths(6),
            'rating' => 4.6,
            'rating_count' => 89,
            'operating_hours' => [
                'monday' => ['open' => '08:00', 'close' => '20:00'],
                'tuesday' => ['open' => '08:00', 'close' => '20:00'],
                'wednesday' => ['open' => '08:00', 'close' => '20:00'],
                'thursday' => ['open' => '08:00', 'close' => '20:00'],
                'friday' => ['open' => '08:00', 'close' => '20:00'],
                'saturday' => ['open' => '09:00', 'close' => '18:00'],
                'sunday' => ['open' => '10:00', 'close' => '16:00'],
            ],
            'delivery_info' => [
                'available' => true,
                'areas' => ['Nairobi', 'Kiambu', 'Machakos'],
                'fee' => 200,
                'free_delivery_threshold' => 3000,
                'estimated_time' => '1-2 business days',
            ],
            'settings' => [
                'currency' => 'KES',
                'tax_rate' => 16,
                'enable_online_store' => true,
                'enable_marketplace' => true,
                'payment_methods' => ['cash', 'mpesa', 'card', 'bank_transfer'],
            ],
            'social_media' => [
                'facebook' => 'https://facebook.com/poachydemo',
                'instagram' => '@poachydemo',
            ],
        ]);

        BusinessSubscription::create([
            'tenant_id' => $tenant->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->addYears(5),
            'amount_paid' => $plan->price,
            'currency' => 'KES',
            'payment_method' => 'mpesa',
            'payment_reference' => 'MPESA-DEMO'.strtoupper(Str::random(8)),
            'payment_date' => now()->subMonths(6),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        app(TenantAccessService::class)->clearTenantAccessCache($tenant->id);
    }

    protected function printSummary(): void
    {
        $this->newLine();
        $this->line('  URL: https://'.self::DOMAIN);
        $this->newLine();

        $this->table(
            ['Role', 'Email', 'Password'],
            collect(DemoStaffSeeder::ACCOUNTS)->map(fn (array $account) => [
                ucfirst($account['role']),
                $account['email'],
                DemoStaffSeeder::PASSWORD,
            ])->all()
        );
    }
}
