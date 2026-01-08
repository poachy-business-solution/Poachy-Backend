<?php

namespace Database\Seeders;

use App\Models\Tenant\TenantSalesSettings;
use Illuminate\Database\Seeder;

class TenantSalesSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        TenantSalesSettings::updateOrCreate(
            [], // No conditions - singleton pattern
            [
                // Loyalty Program Settings
                'loyalty_enabled' => true,
                'loyalty_points_per_currency' => 1.00, // 1 point per KES spent
                'loyalty_redemption_value' => 0.10, // Each point = 0.10 KES
                'loyalty_points_expiry_days' => 365, // Points expire after 1 year
                'loyalty_min_redemption_points' => 100, // Minimum 100 points to redeem
                'loyalty_max_redemption_percentage' => 50.00, // Can redeem max 50% of cart value

                // Coupon & Promotion Settings
                'allow_coupon_promotion_stacking' => false, // One discount at a time

                // Refund Settings
                'refunds_enabled' => true,
                'refund_window_days' => 30, // 30 days refund window
                'refund_requires_receipt' => true, // Receipt mandatory for refunds
                'refund_requires_approval' => true, // Manager approval required
                'refund_approval_threshold' => 5000.00, // Amounts above KES 5,000 need approval

                // Credit Sales Settings
                'credit_sales_enabled' => true,
                'default_credit_limit' => 50000.00, // Default KES 50,000 credit limit
                'credit_payment_terms_days' => 30, // 30 days payment terms

                // Receipt Settings
                'receipt_prefix' => 'INV',
                'refund_receipt_prefix' => 'REF',
                'receipt_footer_message' => 'Thank you for shopping with us! Visit again.',

                // Operational Settings
                'shift_required_for_sales' => true, // Cashiers must clock in
                'prices_include_tax' => true, // Display prices inclusive of tax
            ]
        );
    }
}
