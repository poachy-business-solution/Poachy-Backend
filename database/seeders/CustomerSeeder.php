<?php

namespace Database\Seeders;

use App\Enums\Tenant\CustomerType;
use App\Models\Tenant\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            [
                'email' => 'john.doe@example.com',
                'name' => 'John Doe',
                'phone' => '+254712345601',
                'date_of_birth' => '1990-05-15',
                'address' => '123 Kenyatta Avenue, Nairobi',
                'customer_type' => CustomerType::REGULAR,
                'loyalty_points' => 150,
                'total_lifetime_purchases' => 25000.00,
                'total_visits' => 12,
                'credit_limit' => 10000.00,
                'current_debt' => 0.00,
                'is_active' => true,
            ],
            [
                'email' => 'jane.smith@example.com',
                'name' => 'Jane Smith',
                'phone' => '+254712345602',
                'date_of_birth' => '1985-08-22',
                'address' => '456 Moi Avenue, Nairobi',
                'customer_type' => CustomerType::VIP,
                'loyalty_points' => 500,
                'total_lifetime_purchases' => 125000.00,
                'total_visits' => 45,
                'credit_limit' => 50000.00,
                'current_debt' => 5000.00,
                'is_active' => true,
            ],
            [
                'email' => 'michael.johnson@example.com',
                'name' => 'Michael Johnson',
                'phone' => '+254712345603',
                'date_of_birth' => '1992-03-10',
                'address' => '789 Uhuru Highway, Nairobi',
                'customer_type' => CustomerType::WALK_IN,
                'loyalty_points' => 25,
                'total_lifetime_purchases' => 3500.00,
                'total_visits' => 3,
                'credit_limit' => 0.00,
                'current_debt' => 0.00,
                'is_active' => true,
            ],
            [
                'email' => 'sarah.williams@example.com',
                'name' => 'Sarah Williams',
                'phone' => '+254712345604',
                'date_of_birth' => '1988-11-30',
                'address' => '321 Kimathi Street, Nairobi',
                'customer_type' => CustomerType::REGULAR,
                'loyalty_points' => 200,
                'total_lifetime_purchases' => 45000.00,
                'total_visits' => 20,
                'credit_limit' => 15000.00,
                'current_debt' => 2500.00,
                'is_active' => true,
            ],
            [
                'email' => 'david.brown@example.com',
                'name' => 'David Brown',
                'phone' => '+254712345605',
                'date_of_birth' => '1995-07-18',
                'address' => '654 Ngong Road, Nairobi',
                'customer_type' => CustomerType::VIP,
                'loyalty_points' => 750,
                'total_lifetime_purchases' => 200000.00,
                'total_visits' => 60,
                'credit_limit' => 100000.00,
                'current_debt' => 15000.00,
                'is_active' => true,
            ],
            [
                'email' => 'emma.davis@example.com',
                'name' => 'Emma Davis',
                'phone' => '+254712345606',
                'date_of_birth' => '1993-09-25',
                'address' => '987 Thika Road, Nairobi',
                'customer_type' => CustomerType::WALK_IN,
                'loyalty_points' => 10,
                'total_lifetime_purchases' => 1500.00,
                'total_visits' => 1,
                'credit_limit' => 0.00,
                'current_debt' => 0.00,
                'is_active' => true,
            ],
            [
                'email' => 'james.wilson@example.com',
                'name' => 'James Wilson',
                'phone' => '+254712345607',
                'date_of_birth' => '1987-12-05',
                'address' => '147 Waiyaki Way, Nairobi',
                'customer_type' => CustomerType::REGULAR,
                'loyalty_points' => 300,
                'total_lifetime_purchases' => 65000.00,
                'total_visits' => 28,
                'credit_limit' => 20000.00,
                'current_debt' => 7500.00,
                'is_active' => true,
            ],
            [
                'email' => 'olivia.taylor@example.com',
                'name' => 'Olivia Taylor',
                'phone' => '+254712345608',
                'date_of_birth' => '1991-04-14',
                'address' => '258 Langata Road, Nairobi',
                'customer_type' => CustomerType::REGULAR,
                'loyalty_points' => 180,
                'total_lifetime_purchases' => 35000.00,
                'total_visits' => 15,
                'credit_limit' => 12000.00,
                'current_debt' => 0.00,
                'is_active' => false,
            ],
            [
                'email' => 'william.anderson@example.com',
                'name' => 'William Anderson',
                'phone' => '+254712345609',
                'date_of_birth' => '1989-06-20',
                'address' => '369 Jogoo Road, Nairobi',
                'customer_type' => CustomerType::VIP,
                'loyalty_points' => 900,
                'total_lifetime_purchases' => 300000.00,
                'total_visits' => 80,
                'credit_limit' => 150000.00,
                'current_debt' => 25000.00,
                'is_active' => true,
            ],
            [
                'email' => 'sophia.thomas@example.com',
                'name' => 'Sophia Thomas',
                'phone' => '+254712345610',
                'date_of_birth' => '1994-02-28',
                'address' => '741 Mombasa Road, Nairobi',
                'customer_type' => CustomerType::WALK_IN,
                'loyalty_points' => 5,
                'total_lifetime_purchases' => 800.00,
                'total_visits' => 1,
                'credit_limit' => 0.00,
                'current_debt' => 0.00,
                'is_active' => true,
            ],
        ];

        foreach ($customers as $customerData) {
            Customer::updateOrCreate(
                ['email' => $customerData['email']], // Match on email
                $customerData // Update or create with all data
            );
        }

        $this->command->info('10 customers seeded successfully!');
    }
}
