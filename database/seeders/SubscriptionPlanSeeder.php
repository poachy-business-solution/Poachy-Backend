<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Perfect for getting started with basic POS features',
                'price' => 0.00,
                'currency' => 'KES',
                'billing_cycle_days' => 0, // No billing cycle
                'features' => [
                    'max_products' => 50,
                    'max_users' => 2,
                    'max_locations' => 1,
                    'max_transactions_per_month' => 100,
                    'enable_ecommerce' => false,
                    'enable_marketplace' => false,
                    'enable_analytics' => false,
                    'enable_reports' => 'basic',
                    'transaction_fee_percent' => 0,
                    'support' => 'community',
                    'inventory_tracking' => true,
                    'barcode_scanning' => false,
                    'customer_management' => 'basic',
                ],
                'is_active' => true,
                'is_featured' => false,                
            ],
            [
                'name' => 'Basic',
                'slug' => 'basic',
                'description' => 'Essential features for growing businesses',
                'price' => 2500.00,
                'currency' => 'KES',
                'billing_cycle_days' => 30,
                'features' => [
                    'max_products' => 500,
                    'max_users' => 5,
                    'max_locations' => 2,
                    'max_transactions_per_month' => 1000,
                    'enable_ecommerce' => true,
                    'enable_marketplace' => true,
                    'enable_analytics' => 'basic',
                    'enable_reports' => 'standard',
                    'transaction_fee_percent' => 2,
                    'support' => 'email',
                    'inventory_tracking' => true,
                    'barcode_scanning' => true,
                    'customer_management' => 'standard',
                    'loyalty_program' => false,
                ],
                'is_active' => true,
                'is_featured' => true,                
            ],
            [
                'name' => 'Premium',
                'slug' => 'premium',
                'description' => 'Advanced features for established businesses',
                'price' => 5000.00,
                'currency' => 'KES',
                'billing_cycle_days' => 30,
                'features' => [
                    'max_products' => 2000,
                    'max_users' => 15,
                    'max_locations' => 5,
                    'max_transactions_per_month' => 5000,
                    'enable_ecommerce' => true,
                    'enable_marketplace' => true,
                    'enable_analytics' => 'advanced',
                    'enable_reports' => 'advanced',
                    'transaction_fee_percent' => 1.5,
                    'support' => 'priority',
                    'inventory_tracking' => true,
                    'barcode_scanning' => true,
                    'customer_management' => 'advanced',
                    'loyalty_program' => true,
                    'expense_management' => true,
                    'multi_currency' => true,
                    'api_access' => true,
                ],
                'is_active' => true,
                'is_featured' => true,                
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Complete solution for large-scale operations',
                'price' => 15000.00,
                'currency' => 'KES',
                'billing_cycle_days' => 30,
                'features' => [
                    'max_products' => 'unlimited',
                    'max_users' => 'unlimited',
                    'max_locations' => 'unlimited',
                    'max_transactions_per_month' => 'unlimited',
                    'enable_ecommerce' => true,
                    'enable_marketplace' => true,
                    'enable_analytics' => 'enterprise',
                    'enable_reports' => 'custom',
                    'transaction_fee_percent' => 1,
                    'support' => 'dedicated',
                    'inventory_tracking' => true,
                    'barcode_scanning' => true,
                    'customer_management' => 'enterprise',
                    'loyalty_program' => true,
                    'expense_management' => true,
                    'multi_currency' => true,
                    'api_access' => true,
                    'white_label' => true,
                    'custom_integrations' => true,
                    'dedicated_account_manager' => true,
                ],
                'is_active' => true,
                'is_featured' => false,                
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate($plan);
            $this->command->info("✓ Created subscription plan: {$plan['name']} (KES {$plan['price']})");
        }

        $this->command->info("\n✓ Subscription plans seeded successfully!");
    }
}
