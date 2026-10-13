<?php

namespace App\Jobs\Central;

use App\Mail\Central\Signup\TemporaryPasswordMail;
use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\SignupRequest;
use App\Models\Tenant;
use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use App\Services\Central\Admin\Tenant\TenantService;
use App\Services\Central\Billing\BillingNotices;
use App\Services\Central\Signup\SignupService;
use App\Services\Shared\Messaging\Messenger;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\UnitsOfMeasureSeeder;
use Database\Seeders\UomConversionsSeeder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates a self-signed-up shop: tenant (database, POA account, 30-day trial billing account via
 * TenantObserver), domains, business details, the owner (with the password they chose) and the
 * main branch; then sends the welcome email. Runs once — a failure is recorded on the sign-up
 * for support to see, and the half-made tenant is removed.
 */
class ProvisionSignupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public string $signupId)
    {
        $this->onQueue('default');
    }

    public function handle(TenantService $tenants, SignupService $signups): void
    {
        $signup = SignupRequest::query()->find($this->signupId);

        if ($signup === null || $signup->status !== SignupRequest::PROVISIONING || $signup->tenant_id !== null) {
            return;
        }

        $tenant = null;

        try {
            $domains = array_map(fn (string $t) => str_replace('{slug}', $signup->workspace, $t), config('signup.domains'));

            $tenant = $tenants->createTenant([
                'domain' => $domains[0],
                'additional_domains' => array_slice($domains, 1),
                'tenant_name' => $signup->shop_name,
                'notes' => 'Self sign-up '.$signup->id,
            ]);

            $categoryId = $signups->businessCategoryIdFor($signup);
            BusinessDetail::query()->create([
                'tenant_id' => $tenant->id,
                'business_name' => $signup->shop_name,
                'business_type_id' => BusinessCategory::query()->whereKey($categoryId)->value('business_type_id'),
                'business_category_id' => $categoryId,
                'business_phone' => '+'.$signup->phone,
                'business_email' => $signup->email,
                'contact_person' => $signup->owner_name,
                'city' => $signup->town,
                'status' => 'active',
                'is_verified' => false,
                'onboarded_at' => now(),
            ]);

            // Shops opened by staff: a one-time temporary password, emailed to the owner and never
            // stored in plain text; the app makes them choose their own on first sign-in.
            $byAdmin = $signup->source === SignupRequest::SOURCE_ADMIN;
            $temporaryPassword = $byAdmin ? Str::password(14, symbols: false) : null;

            // The tenant database was seeded before its business details existed, so the catalogue
            // template (product categories + units of measure for its type of shop) was skipped.
            $tenant->run(function () {
                foreach ([ProductCategorySeeder::class, UnitsOfMeasureSeeder::class, UomConversionsSeeder::class] as $seeder) {
                    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                }
            });

            $tenant->run(function () use ($signup, $temporaryPassword) {
                $owner = User::query()->create([
                    'name' => $signup->owner_name,
                    'email' => $signup->email,
                    'phone' => '+'.$signup->phone,
                    'password' => $temporaryPassword ?? $signup->password_hash, // a hash is not re-hashed by the cast
                    'is_active' => true,
                ]);
                if ($temporaryPassword === null) {
                    $owner->forceFill(['password_chosen_at' => now()])->save();
                }
                $owner->assignRole('owner');

                Store::query()->create([
                    'name' => $signup->shop_name,
                    'city' => $signup->town,
                    'address' => $signup->town,
                    'phone' => '+'.$signup->phone,
                    'email' => $signup->email,
                    'is_main_store' => true,
                    'is_active' => true,
                ]);
            });

            $signup->forceFill(['status' => SignupRequest::READY, 'tenant_id' => $tenant->id, 'password_hash' => null])->save();

            $this->welcome($signup, $tenant->fresh(), $temporaryPassword);
        } catch (Throwable $e) {
            Log::error('Self sign-up provisioning failed', ['signup_id' => $signup->id, 'error' => $e->getMessage()]);

            if ($tenant !== null) {
                try {
                    $tenants->deleteTenant((string) $tenant->id);
                } catch (Throwable $cleanup) {
                    Log::error('Could not remove the half-made tenant', ['tenant_id' => $tenant->id, 'error' => $cleanup->getMessage()]);
                }
            }

            $signup->forceFill(['status' => SignupRequest::FAILED, 'failure_reason' => mb_substr($e->getMessage(), 0, 250), 'tenant_id' => null])->save();
        }
    }

    private function welcome(SignupRequest $signup, Tenant $tenant, ?string $temporaryPassword): void
    {
        app(BillingNotices::class)->welcome($tenant, $signup->email, $signup->owner_name, $signup->workspace, $temporaryPassword !== null);

        if ($temporaryPassword !== null) {
            app(Messenger::class)->email($signup->email, new TemporaryPasswordMail($signup->owner_name, $signup->workspace, $signup->email, $temporaryPassword), (string) $tenant->id, 'signup_temporary_password');
        }
    }
}
