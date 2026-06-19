<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CentralAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure we're on central connection
        config(['permission.connection' => 'central']);

        DB::connection('central')->transaction(function () {
            // Create first admin user
            $admin = User::create([
                'name' => 'Admin',
                'email' => 'admin@poachy.com',
                'password' => Hash::make('password'),
                'user_type' => 'admin',
                'email_verified_at' => now(),
            ]);

            // Assign admin role
            $admin->assignRole('admin');

            $this->command->info('✓ Created Super Admin: ' . $admin->email);
        });
    }
}
