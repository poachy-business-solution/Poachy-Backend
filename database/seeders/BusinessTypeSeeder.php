<?php

namespace Database\Seeders;

use App\Models\BusinessCategory;
use App\Models\BusinessType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Retail & Consumer Goods',
                'slug' => 'retail-consumer-goods',
                'description' => 'Businesses selling physical products directly to consumers',                
                'categories' => [
                    ['name' => 'Supermarket', 'slug' => 'supermarket', 'description' => 'Large self-service stores offering groceries and household items'],
                    ['name' => 'Grocery Store', 'slug' => 'grocery-store', 'description' => 'Small to medium stores selling food and household supplies'],
                    ['name' => 'Electronics Shop', 'slug' => 'electronics-shop', 'description' => 'Stores specializing in consumer electronics and gadgets'],
                    ['name' => 'Clothing & Fashion', 'slug' => 'clothing-fashion', 'description' => 'Boutiques and stores selling apparel and accessories'],
                    ['name' => 'Hardware Store', 'slug' => 'hardware-store', 'description' => 'Stores selling construction materials and tools'],
                    ['name' => 'Pharmacy', 'slug' => 'pharmacy', 'description' => 'Drug stores and chemists'],
                    ['name' => 'Bookstore', 'slug' => 'bookstore', 'description' => 'Stores selling books and stationery'],
                    ['name' => 'Beauty & Cosmetics', 'slug' => 'beauty-cosmetics', 'description' => 'Stores selling beauty products and cosmetics'],
                ],
            ],
            [
                'name' => 'Food & Beverage',
                'slug' => 'food-beverage',
                'description' => 'Businesses serving food and drinks',                     
                'categories' => [
                    ['name' => 'Restaurant', 'slug' => 'restaurant', 'description' => 'Full-service dining establishments'],
                    ['name' => 'Fast Food', 'slug' => 'fast-food', 'description' => 'Quick-service restaurants'],
                    ['name' => 'Café & Coffee Shop', 'slug' => 'cafe-coffee-shop', 'description' => 'Coffee houses and casual cafés'],
                    ['name' => 'Bakery', 'slug' => 'bakery', 'description' => 'Stores specializing in baked goods'],
                    ['name' => 'Bar & Lounge', 'slug' => 'bar-lounge', 'description' => 'Establishments serving alcoholic beverages'],
                    ['name' => 'Food Truck', 'slug' => 'food-truck', 'description' => 'Mobile food vendors'],
                ],
            ],
            [
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Service-based businesses',                   
                'categories' => [
                    ['name' => 'Salon & Spa', 'slug' => 'salon-spa', 'description' => 'Hair, beauty, and wellness services'],
                    ['name' => 'Laundry & Dry Cleaning', 'slug' => 'laundry-dry-cleaning', 'description' => 'Clothing cleaning services'],
                    ['name' => 'Car Wash', 'slug' => 'car-wash', 'description' => 'Vehicle cleaning services'],
                    ['name' => 'Repair Services', 'slug' => 'repair-services', 'description' => 'Electronics and appliance repair'],
                    ['name' => 'Fitness Center', 'slug' => 'fitness-center', 'description' => 'Gyms and fitness studios'],
                    ['name' => 'Photography Studio', 'slug' => 'photography-studio', 'description' => 'Professional photography services'],
                ],
            ],
            [
                'name' => 'Health & Wellness',
                'slug' => 'health-wellness',
                'description' => 'Healthcare and wellness providers',               
                'categories' => [
                    ['name' => 'Clinic', 'slug' => 'clinic', 'description' => 'Medical clinics and health centers'],
                    ['name' => 'Dental Office', 'slug' => 'dental-office', 'description' => 'Dental care providers'],
                    ['name' => 'Veterinary Clinic', 'slug' => 'veterinary-clinic', 'description' => 'Animal healthcare services'],
                    ['name' => 'Laboratory', 'slug' => 'laboratory', 'description' => 'Medical testing facilities'],
                ],
            ],
            [
                'name' => 'Automotive',
                'slug' => 'automotive',
                'description' => 'Vehicle sales and services',                              
                'categories' => [
                    ['name' => 'Auto Parts Store', 'slug' => 'auto-parts-store', 'description' => 'Vehicle parts and accessories'],
                    ['name' => 'Car Dealership', 'slug' => 'car-dealership', 'description' => 'New and used car sales'],
                    ['name' => 'Auto Repair Shop', 'slug' => 'auto-repair-shop', 'description' => 'Vehicle maintenance and repair'],
                    ['name' => 'Tire Shop', 'slug' => 'tire-shop', 'description' => 'Tire sales and services'],
                ],
            ],
        ];

        foreach ($types as $typeData) {
            $categories = $typeData['categories'];
            unset($typeData['categories']);

            $type = BusinessType::updateOrCreate($typeData);
            
            $this->command->info("✓ Created business type: {$type->name}");

            foreach ($categories as $index => $categoryData) {
                $categoryData['business_type_id'] = $type->id;                
                
                $category = BusinessCategory::updateOrCreate($categoryData);
                $this->command->info("  → Category: {$category->name}");
            }
        }

        $this->command->info("\n✓ Business types and categories seeded successfully!");
    }
}
