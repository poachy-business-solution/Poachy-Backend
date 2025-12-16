<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\ProductBrand;
use Illuminate\Support\Carbon;

class ProductBrandSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        /**
         * Marketplace-wide standard brands
         * Covers: electronics, FMCG, fashion, automotive, appliances, tools, services
         */
        $brands = [
            // Electronics & Technology
            ['name' => 'Samsung', 'description' => 'Consumer electronics, smartphones, and appliances', 'is_featured' => true, 'display_order' => 1],
            ['name' => 'Apple', 'description' => 'Premium smartphones, computers, and consumer electronics', 'is_featured' => true, 'display_order' => 2],
            ['name' => 'Huawei', 'description' => 'Smartphones, networking, and electronic devices', 'is_featured' => true, 'display_order' => 3],
            ['name' => 'Tecno', 'description' => 'Affordable smartphones and mobile devices', 'is_featured' => true, 'display_order' => 4],
            ['name' => 'Infinix', 'description' => 'Smartphones and mobile accessories', 'is_featured' => false, 'display_order' => 5],
            ['name' => 'HP', 'description' => 'Computers, printers, and office electronics', 'is_featured' => false, 'display_order' => 6],
            ['name' => 'Dell', 'description' => 'Laptops, desktops, and enterprise computing devices', 'is_featured' => false, 'display_order' => 7],
            ['name' => 'Lenovo', 'description' => 'Computers, laptops, and business electronics', 'is_featured' => false, 'display_order' => 8],

            // FMCG / Groceries
            ['name' => 'Unilever', 'description' => 'Fast-moving consumer goods and household products', 'is_featured' => true, 'display_order' => 20],
            ['name' => 'Nestlé', 'description' => 'Food, beverages, and nutrition products', 'is_featured' => true, 'display_order' => 21],
            ['name' => 'Coca-Cola', 'description' => 'Soft drinks and beverage products', 'is_featured' => true, 'display_order' => 22],
            ['name' => 'Pepsi', 'description' => 'Beverages and snack products', 'is_featured' => false, 'display_order' => 23],
            ['name' => 'Brookside', 'description' => 'Dairy and milk-based products', 'is_featured' => false, 'display_order' => 24],

            // Fashion & Apparel
            ['name' => 'Nike', 'description' => 'Sportswear, footwear, and apparel', 'is_featured' => true, 'display_order' => 40],
            ['name' => 'Adidas', 'description' => 'Athletic apparel, footwear, and accessories', 'is_featured' => true, 'display_order' => 41],
            ['name' => 'Puma', 'description' => 'Sportswear and casual fashion products', 'is_featured' => false, 'display_order' => 42],
            ['name' => 'Levi’s', 'description' => 'Denim jeans and casual wear', 'is_featured' => false, 'display_order' => 43],

            // Home Appliances & Living
            ['name' => 'LG', 'description' => 'Home appliances and consumer electronics', 'is_featured' => true, 'display_order' => 60],
            ['name' => 'Hisense', 'description' => 'Televisions and home appliances', 'is_featured' => false, 'display_order' => 61],
            ['name' => 'Ramtons', 'description' => 'Affordable home and kitchen appliances', 'is_featured' => false, 'display_order' => 62],

            // Automotive
            ['name' => 'Toyota', 'description' => 'Vehicles and automotive spare parts', 'is_featured' => true, 'display_order' => 80],
            ['name' => 'Bosch', 'description' => 'Automotive parts, tools, and electronics', 'is_featured' => false, 'display_order' => 81],
            ['name' => 'TotalEnergies', 'description' => 'Lubricants, fuels, and automotive fluids', 'is_featured' => false, 'display_order' => 82],

            // Hardware & Tools
            ['name' => 'Makita', 'description' => 'Power tools and industrial equipment', 'is_featured' => false, 'display_order' => 100],
            ['name' => 'Bosch Tools', 'description' => 'Professional and home-use tools', 'is_featured' => false, 'display_order' => 101],

            // Generic / Marketplace Brands
            ['name' => 'Generic', 'description' => 'Unbranded or locally manufactured products', 'is_featured' => false, 'display_order' => 200],
            ['name' => 'Local Brand', 'description' => 'Locally produced and small-scale manufacturer products', 'is_featured' => false, 'display_order' => 201],
        ];

        foreach ($brands as $brand) {
            ProductBrand::updateOrCreate(
                [
                    'name' => $brand['name'],
                ],
                [
                    'description' => $brand['description'],
                    'logo_url' => null,
                    'is_active' => true,
                    'is_featured' => $brand['is_featured'],
                    'display_order' => $brand['display_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
}
