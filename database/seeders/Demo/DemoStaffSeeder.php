<?php

namespace Database\Seeders\Demo;

use App\Models\Tenant\Store;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStaffSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';

    public const ACCOUNTS = [
        ['role' => 'owner', 'name' => 'Amina Wanjiru', 'email' => 'owner@demo.poachy.test', 'phone' => '+254711000001'],
        ['role' => 'manager', 'name' => 'Brian Otieno', 'email' => 'manager@demo.poachy.test', 'phone' => '+254711000002'],
        ['role' => 'cashier', 'name' => 'Faith Mwangi', 'email' => 'cashier1@demo.poachy.test', 'phone' => '+254711000003'],
        ['role' => 'cashier', 'name' => 'Kevin Njoroge', 'email' => 'cashier2@demo.poachy.test', 'phone' => '+254711000004'],
    ];

    public function run(): void
    {
        $hashedPassword = Hash::make(self::PASSWORD);
        $usersByRole = [];

        foreach (self::ACCOUNTS as $account) {
            $user = User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'phone' => $account['phone'],
                    'password' => $hashedPassword,
                    'is_active' => true,
                ]
            );

            $user->syncRoles([$account['role']]);

            $usersByRole[$account['role']] ??= $user;

            $this->command->info("✓ Staff user: {$account['name']} ({$account['role']})");
        }

        $owner = $usersByRole['owner'];
        $manager = $usersByRole['manager'];

        Store::create([
            'name' => 'Poachy Demo Store — Nairobi CBD',
            'code' => Store::generateUniqueCode(),
            'description' => 'Main branch, ground floor',
            'address' => 'Demo Plaza, Moi Avenue',
            'city' => 'Nairobi',
            'region' => 'Nairobi',
            'phone' => '+254700000000',
            'email' => 'cbd@demo.poachy.test',
            'is_main_store' => true,
            'is_active' => true,
            'manager_id' => $manager->id,
            'created_by' => $owner->id,
        ]);

        Store::create([
            'name' => 'Poachy Demo Store — Westlands',
            'code' => Store::generateUniqueCode(),
            'description' => 'Branch store',
            'address' => 'Westlands Mall, 2nd Floor',
            'city' => 'Nairobi',
            'region' => 'Nairobi',
            'phone' => '+254700000001',
            'email' => 'westlands@demo.poachy.test',
            'is_main_store' => false,
            'is_active' => true,
            'manager_id' => $manager->id,
            'created_by' => $owner->id,
        ]);

        $this->command->info('✓ Created 2 stores');
    }
}
