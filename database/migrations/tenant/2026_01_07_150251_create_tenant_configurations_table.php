<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_configurations', function (Blueprint $table) {
            $table->id();

            $table->string('config_key')->unique();
            $table->json('config_value');
            $table->string('config_type')->default('general'); // general, loyalty, credit, sales, receipt, etc.
            $table->string('config_group')->nullable(); // For grouping related configs
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['config_key', 'is_active']);
            $table->index('config_group');
        });

        // Seed default configurations
        $this->seedDefaultConfigurations();
    }

    protected function seedDefaultConfigurations(): void
    {
        $defaults = [
            // Loyalty Configuration
            [
                'config_key' => 'loyalty_enabled',
                'config_value' => json_encode(true),
                'config_type' => 'loyalty',
                'config_group' => 'loyalty',
                'description' => 'Enable/disable loyalty program',
            ],
            [
                'config_key' => 'loyalty_earning_rate',
                'config_value' => json_encode(0.01), // 1% of purchase
                'config_type' => 'loyalty',
                'config_group' => 'loyalty',
                'description' => 'Points earning rate (percentage of purchase amount)',
            ],
            [
                'config_key' => 'loyalty_redemption_rate',
                'config_value' => json_encode(1.0), // 1 point = KES 1
                'config_type' => 'loyalty',
                'config_group' => 'loyalty',
                'description' => 'Points to currency conversion rate',
            ],
            [
                'config_key' => 'loyalty_points_expiry_days',
                'config_value' => json_encode(365), // 1 year
                'config_type' => 'loyalty',
                'config_group' => 'loyalty',
                'description' => 'Number of days before earned points expire',
            ],
            [
                'config_key' => 'loyalty_min_redemption_points',
                'config_value' => json_encode(100),
                'config_type' => 'loyalty',
                'config_group' => 'loyalty',
                'description' => 'Minimum points required for redemption',
            ],

            // Credit Configuration
            [
                'config_key' => 'credit_enabled',
                'config_value' => json_encode(true),
                'config_type' => 'credit',
                'config_group' => 'credit',
                'description' => 'Enable/disable credit sales',
            ],
            [
                'config_key' => 'credit_default_limit',
                'config_value' => json_encode(10000), // KES 10,000
                'config_type' => 'credit',
                'config_group' => 'credit',
                'description' => 'Default credit limit for new customers',
            ],
            [
                'config_key' => 'credit_grace_period_days',
                'config_value' => json_encode(30),
                'config_type' => 'credit',
                'config_group' => 'credit',
                'description' => 'Grace period before credit becomes overdue',
            ],

            // Sales & Offers Configuration
            [
                'config_key' => 'allow_coupon_promotion_stacking',
                'config_value' => json_encode(false),
                'config_type' => 'sales',
                'config_group' => 'offers',
                'description' => 'Allow coupons and promotions to be used together',
            ],
            [
                'config_key' => 'coupon_priority_over_promotion',
                'config_value' => json_encode(true),
                'config_type' => 'sales',
                'config_group' => 'offers',
                'description' => 'If stacking disabled, prioritize coupon over promotion',
            ],

            // Receipt Configuration
            [
                'config_key' => 'receipt_enabled',
                'config_value' => json_encode(true),
                'config_type' => 'receipt',
                'config_group' => 'receipt',
                'description' => 'Enable/disable receipt generation',
            ],
            [
                'config_key' => 'receipt_auto_print',
                'config_value' => json_encode(true),
                'config_type' => 'receipt',
                'config_group' => 'receipt',
                'description' => 'Automatically print receipt after sale',
            ],
            [
                'config_key' => 'receipt_include_logo',
                'config_value' => json_encode(true),
                'config_type' => 'receipt',
                'config_group' => 'receipt',
                'description' => 'Include store logo on receipt',
            ],

            // Customer Upgrades
            [
                'config_key' => 'customer_auto_upgrade_enabled',
                'config_value' => json_encode(true),
                'config_type' => 'customer',
                'config_group' => 'customer',
                'description' => 'Automatically upgrade customer types based on purchase thresholds',
            ],
        ];

        DB::table('tenant_configurations')->insert($defaults);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_configurations');
    }
};
