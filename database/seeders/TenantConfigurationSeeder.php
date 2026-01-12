<?php

namespace Database\Seeders;

use App\Models\Tenant\TenantConfiguration;
use Illuminate\Database\Seeder;

class TenantConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            // ============================================
            // STOCK ALERT CONFIGURATION
            // ============================================
            [
                'config_key' => 'stock_alerts_enabled',
                'config_value' => true,
                'config_type' => 'inventory',
                'config_group' => 'stock_alerts',
                'description' => 'Enable/disable automatic stock alert generation',
            ],
            [
                'config_key' => 'stock_alerts_notification_channel',
                'config_value' => 'email', // email, sms, push
                'config_type' => 'inventory',
                'config_group' => 'stock_alerts',
                'description' => 'Notification channel for stock alerts',
            ],

            // ============================================
            // EXPIRY ALERT CONFIGURATION
            // ============================================
            [
                'config_key' => 'expiry_alerts_enabled',
                'config_value' => true,
                'config_type' => 'inventory',
                'config_group' => 'expiry_alerts',
                'description' => 'Enable/disable automatic expiry alert generation',
            ],
            [
                'config_key' => 'expiry_alerts_warning_days',
                'config_value' => 60, // 60 days before expiry
                'config_type' => 'inventory',
                'config_group' => 'expiry_alerts',
                'description' => 'Days before expiry to trigger warning alert',
            ],
            [
                'config_key' => 'expiry_alerts_urgent_days',
                'config_value' => 30, // 30 days before expiry
                'config_type' => 'inventory',
                'config_group' => 'expiry_alerts',
                'description' => 'Days before expiry to trigger urgent alert',
            ],
            [
                'config_key' => 'expiry_alerts_notification_channel',
                'config_value' => 'email', // email, sms, push
                'config_type' => 'inventory',
                'config_group' => 'expiry_alerts',
                'description' => 'Notification channel for expiry alerts',
            ],

            // ============================================
            // INVENTORY WASTE CONFIGURATION
            // ============================================
            [
                'config_key' => 'waste_approval_required',
                'config_value' => true,
                'config_type' => 'inventory',
                'config_group' => 'inventory_waste',
                'description' => 'Require manager approval for waste records',
            ],
            [
                'config_key' => 'waste_notification_channel',
                'config_value' => 'email', // email, sms, push
                'config_type' => 'inventory',
                'config_group' => 'inventory_waste',
                'description' => 'Notification channel for waste approvals',
            ],
        ];

        foreach ($configurations as $config) {
            TenantConfiguration::updateOrCreate(
                ['config_key' => $config['config_key']],
                $config
            );
        }
    }
}
