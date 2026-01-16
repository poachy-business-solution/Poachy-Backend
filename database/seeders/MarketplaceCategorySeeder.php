<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class MarketplaceCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        /**
         * Parent categories
         */
        $parents = [
            [
                'key' => 'electronics',
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Electronic devices, gadgets, and accessories',
                'display_order' => 1,
                'is_featured' => true,
            ],
            [
                'key' => 'groceries',
                'name' => 'Groceries',
                'slug' => 'groceries',
                'description' => 'Everyday food items and household consumables',
                'display_order' => 2,
                'is_featured' => true,
            ],
            [
                'key' => 'fashion',
                'name' => 'Fashion',
                'slug' => 'fashion',
                'description' => 'Clothing, footwear, and wearable accessories',
                'display_order' => 3,
                'is_featured' => true,
            ],
            [
                'key' => 'health',
                'name' => 'Health & Beauty',
                'slug' => 'health-beauty',
                'description' => 'Health, beauty, and personal care products',
                'display_order' => 4,
                'is_featured' => true,
            ],
            [
                'key' => 'home',
                'name' => 'Home & Living',
                'slug' => 'home-living',
                'description' => 'Furniture, home décor, and household essentials',
                'display_order' => 5,
                'is_featured' => false,
            ],
            [
                'key' => 'hardware',
                'name' => 'Hardware & Construction',
                'slug' => 'hardware-construction',
                'description' => 'Building materials, tools, and construction supplies',
                'display_order' => 6,
                'is_featured' => false,
            ],
            [
                'key' => 'automotive',
                'name' => 'Automotive',
                'slug' => 'automotive',
                'description' => 'Vehicle parts, accessories, and maintenance products',
                'display_order' => 7,
                'is_featured' => false,
            ],
            [
                'key' => 'hospitality',
                'name' => 'Hospitality & Food Service',
                'slug' => 'hospitality-food-service',
                'description' => 'Restaurant, hotel, and catering supplies',
                'display_order' => 8,
                'is_featured' => false,
            ],
            [
                'key' => 'office',
                'name' => 'Office & Stationery',
                'slug' => 'office-stationery',
                'description' => 'Office supplies, stationery, and business essentials',
                'display_order' => 9,
                'is_featured' => false,
            ],
            [
                'key' => 'services',
                'name' => 'Services',
                'slug' => 'services',
                'description' => 'Non-physical products and professional services',
                'display_order' => 10,
                'is_featured' => false,
            ],
            [
                'key' => 'uncategorized',
                'name' => 'Uncategorized',
                'slug' => 'uncategorized',
                'description' => 'Products that do not fit into any category',
                'display_order' => 11,
                'is_featured' => false,
            ],
        ];

        /**
         * Insert parents & keep ID references
         */
        $parentIds = [];

        foreach ($parents as $parent) {
            $id = DB::connection('central')->table('marketplace_categories')->insertGetId([
                'name' => $parent['name'],
                'slug' => $parent['slug'],
                'description' => $parent['description'],
                'icon' => null,
                'banner_image' => null,
                'parent_id' => null,
                'display_order' => $parent['display_order'],
                'is_featured' => $parent['is_featured'],
                'is_active' => true,
                'meta_title' => $parent['name'] . ' - Marketplace Categories',
                'meta_description' => $parent['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $parentIds[$parent['key']] = $id;
        }

        /**
         * Child categories
         */
        $children = [
            // Electronics
            ['parent' => 'electronics', 'name' => 'Mobile Phones', 'description' => 'Smartphones and feature phones', 'display_order' => 1],
            ['parent' => 'electronics', 'name' => 'Computers & Laptops', 'description' => 'Desktops, laptops, and computing devices', 'display_order' => 2],
            ['parent' => 'electronics', 'name' => 'Phone Accessories', 'description' => 'Chargers, cases, earphones, and accessories', 'display_order' => 3],

            // Groceries
            ['parent' => 'groceries', 'name' => 'Fresh Produce', 'description' => 'Fresh fruits and vegetables', 'display_order' => 1],
            ['parent' => 'groceries', 'name' => 'Packaged Foods', 'description' => 'Rice, flour, cereals, and packaged meals', 'display_order' => 2],
            ['parent' => 'groceries', 'name' => 'Beverages', 'description' => 'Soft drinks, juices, and bottled beverages', 'display_order' => 3],

            // Fashion
            ['parent' => 'fashion', 'name' => 'Mens Fashion', 'description' => 'Clothing and apparel for men', 'display_order' => 1],
            ['parent' => 'fashion', 'name' => 'Womens Fashion', 'description' => 'Clothing and apparel for women', 'display_order' => 2],
            ['parent' => 'fashion', 'name' => 'Footwear', 'description' => 'Shoes, sandals, and sneakers', 'display_order' => 3],

            // Health & Beauty
            ['parent' => 'health', 'name' => 'Personal Care', 'description' => 'Soaps, lotions, and personal hygiene products', 'display_order' => 1],
            ['parent' => 'health', 'name' => 'Cosmetics', 'description' => 'Makeup and beauty enhancement products', 'display_order' => 2],
            ['parent' => 'health', 'name' => 'Pharmacy', 'description' => 'Over-the-counter medicines and supplements', 'display_order' => 3],

            // Home & Living
            ['parent' => 'home', 'name' => 'Furniture', 'description' => 'Home and office furniture', 'display_order' => 1],
            ['parent' => 'home', 'name' => 'Home Décor', 'description' => 'Decorative items and interior accessories', 'display_order' => 2],
            ['parent' => 'home', 'name' => 'Kitchenware', 'description' => 'Utensils, cookware, and kitchen tools', 'display_order' => 3],

            // Hardware & Construction
            ['parent' => 'hardware', 'name' => 'Building Materials', 'description' => 'Cement, timber, and construction materials', 'display_order' => 1],
            ['parent' => 'hardware', 'name' => 'Tools & Equipment', 'description' => 'Hand tools and power tools', 'display_order' => 2],

            // Automotive
            ['parent' => 'automotive', 'name' => 'Vehicle Parts', 'description' => 'Spare parts and vehicle components', 'display_order' => 1],
            ['parent' => 'automotive', 'name' => 'Car Accessories', 'description' => 'Interior and exterior vehicle accessories', 'display_order' => 2],

            // Hospitality & Food Service
            ['parent' => 'hospitality', 'name' => 'Restaurant Supplies', 'description' => 'Kitchen and serving equipment', 'display_order' => 1],
            ['parent' => 'hospitality', 'name' => 'Catering Equipment', 'description' => 'Large-scale food preparation equipment', 'display_order' => 2],

            // Office & Stationery
            ['parent' => 'office', 'name' => 'Stationery', 'description' => 'Paper, pens, and writing materials', 'display_order' => 1],
            ['parent' => 'office', 'name' => 'Office Equipment', 'description' => 'Printers, scanners, and office machines', 'display_order' => 2],

            // Services
            ['parent' => 'services', 'name' => 'Professional Services', 'description' => 'Consulting, accounting, and legal services', 'display_order' => 1],
            ['parent' => 'services', 'name' => 'Repair & Maintenance', 'description' => 'Repair, installation, and maintenance services', 'display_order' => 2],
        ];

        /**
         * Insert children
         */
        foreach ($children as $child) {
            $slug = Str::slug($child['name']);

            DB::connection('central')->table('marketplace_categories')->insert([
                'name' => $child['name'],
                'slug' => $slug,
                'description' => $child['description'],
                'icon' => null,
                'banner_image' => null,
                'parent_id' => $parentIds[$child['parent']],
                'display_order' => $child['display_order'],
                'is_featured' => false,
                'is_active' => true,
                'meta_title' => $child['name'] . ' - Marketplace Categories',
                'meta_description' => $child['description'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
