<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use App\Models\BusinessDetail;
use App\Models\BusinessSubscription;
use App\Models\Domain;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get necessary data
        $electronicsCat = BusinessCategory::where('slug', 'electronics-shop')->first();
        $groceryCat = BusinessCategory::where('slug', 'grocery-store')->first();
        $premiumPlan = SubscriptionPlan::where('slug', 'premium')->first();
        $basicPlan = SubscriptionPlan::where('slug', 'basic')->first();

        // Tenant 1: Tech Haven - Electronics Store
        $tenant1 = Tenant::create([
            'id' => Str::uuid()->toString(),
        ]);

        BusinessDetail::create([
            'tenant_id' => $tenant1->id,
            'business_name' => 'Tech Haven Electronics',
            'business_description' => 'Your one-stop shop for the latest electronics, smartphones, laptops, and tech accessories. Quality products at competitive prices.',
            'business_logo' => 'logos/techhaven.png',
            'business_banner' => 'banners/techhaven.jpg',
            'business_type_id' => $electronicsCat->business_type_id,
            'business_category_id' => $electronicsCat->id,
            'business_email' => 'info@techhaven.poachy.com',
            'business_phone' => '+254712345678',            
            'contact_person' => 'John Kamau',
            'address' => 'Kimathi Street Plaza, Ground Floor',
            'city' => 'Nairobi',
            'county' => 'Nairobi',                           
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now()->subDays(15),
            'onboarded_at' => now()->subMonths(3),
            'rating' => 4.75,
            'rating_count' => 127,
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
                'areas' => ['Nairobi', 'Kiambu', 'Machakos', 'Kajiado'],
                'fee' => 200,
                'free_delivery_threshold' => 5000,
                'estimated_time' => '1-3 business days',
            ],
            'settings' => [
                'currency' => 'KES',
                'tax_rate' => 16,
                'enable_online_store' => true,
                'enable_marketplace' => true,
                'payment_methods' => ['cash', 'mpesa', 'card', 'bank_transfer'],
            ],
            'social_media' => [
                'facebook' => 'https://facebook.com/techhaven',
                'instagram' => '@techhaven_ke',
                'twitter' => '@TechHavenKE',
                'whatsapp' => '+254712345678',
            ],
        ]);

        BusinessSubscription::create([
            'tenant_id' => $tenant1->id,
            'subscription_plan_id' => $premiumPlan->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(9),
            'amount_paid' => $premiumPlan->price,
            'currency' => 'KES',
            'payment_method' => 'mpesa',
            'payment_reference' => 'MPESA-' . strtoupper(Str::random(10)),
            'payment_date' => now()->subMonths(3),
            'status' => 'active',
            'auto_renew' => true,
        ]);

        Domain::create([
            'domain' => 'techhaven.localhost', // For local development
            'tenant_id' => $tenant1->id,
        ]);

        $this->command->info("✓ Created tenant: Tech Haven Electronics");

        $this->command->info("\n✓ Tenant seeding completed successfully!");
    }
}
