<?php

namespace Database\Seeders\Demo;

use App\Enums\Tenant\CouponApplicabilityType;
use App\Enums\Tenant\DiscountType;
use App\Enums\Tenant\PromotionApplicabilityType;
use App\Enums\Tenant\PromotionType;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductCategory;
use App\Services\Tenant\Offers\CouponService;
use App\Services\Tenant\Offers\PromotionService;
use Illuminate\Database\Seeder;

class DemoPromotionSeeder extends Seeder
{
    public function run(CouponService $couponService, PromotionService $promotionService): void
    {
        $mobilePhones = ProductCategory::where('name', 'Mobile Phones')->firstOrFail()->id;
        $computers = ProductCategory::where('name', 'Computers & Laptops')->firstOrFail()->id;
        $beverages = ProductCategory::where('name', 'Beverages')->firstOrFail()->id;
        $nike = ProductBrand::where('name', 'Nike')->firstOrFail()->id;
        $adidas = ProductBrand::where('name', 'Adidas')->firstOrFail()->id;

        $couponService->createCoupon([
            'code' => 'WELCOME10',
            'description' => '10% off for new and returning customers, storewide.',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 10,
            'min_purchase_amount' => 500,
            'usage_limit' => 200,
            'usage_limit_per_customer' => 1,
            'valid_from' => now()->subDays(30)->toDateString(),
            'valid_until' => now()->addDays(60)->toDateString(),
            'applicable_to' => CouponApplicabilityType::ALL_PRODUCTS,
            'is_active' => true,
        ]);

        $couponService->createCoupon([
            'code' => 'ELECTRONICS15',
            'description' => '15% off phones, laptops, and computers.',
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => 15,
            'min_purchase_amount' => 5000,
            'max_discount_amount' => 5000,
            'usage_limit' => 100,
            'usage_limit_per_customer' => 2,
            'valid_from' => now()->subDays(15)->toDateString(),
            'valid_until' => now()->addDays(45)->toDateString(),
            'applicable_to' => CouponApplicabilityType::SPECIFIC_CATEGORIES,
            'is_active' => true,
            'applicability' => ['categories' => [$mobilePhones, $computers]],
        ]);

        $couponService->createCoupon([
            'code' => 'FASHION500',
            'description' => 'KES 500 off Nike and Adidas gear.',
            'discount_type' => DiscountType::FIXED_AMOUNT,
            'discount_value' => 500,
            'min_purchase_amount' => 2000,
            'usage_limit' => 50,
            'usage_limit_per_customer' => 1,
            'valid_from' => now()->subDays(10)->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'applicable_to' => CouponApplicabilityType::SPECIFIC_BRANDS,
            'is_active' => true,
            'applicability' => ['brands' => [$nike, $adidas]],
        ]);

        $groceryProducts = Product::whereIn('name', ['Pishori Rice 2kg', 'Nestlé Cerelac 400g'])
            ->pluck('id')
            ->map(fn (int $id) => ['product_id' => $id, 'product_variant_id' => null])
            ->all();

        $couponService->createCoupon([
            'code' => 'GROCERY100',
            'description' => 'KES 100 off rice and Cerelac.',
            'discount_type' => DiscountType::FIXED_AMOUNT,
            'discount_value' => 100,
            'min_purchase_amount' => 300,
            'usage_limit' => 100,
            'usage_limit_per_customer' => 2,
            'valid_from' => now()->subDays(10)->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'applicable_to' => CouponApplicabilityType::SPECIFIC_PRODUCTS,
            'is_active' => true,
            'applicability' => ['products' => $groceryProducts],
        ]);

        $breakfastProducts = Product::whereIn('name', [
            'Brookside Fresh Milk 500ml',
            'Nestlé Cerelac 400g',
            'Unilever Blue Band 500g',
        ])->pluck('id')->map(fn (int $id) => ['product_id' => $id, 'product_variant_id' => null])->all();

        $promotionService->createPromotion([
            'name' => 'Breakfast Essentials Discount',
            'code' => 'BREAKFAST10',
            'description' => '10% off milk, cerelac, and blue band.',
            'promotion_type' => PromotionType::PERCENTAGE_DISCOUNT,
            'discount_value' => 10,
            'min_purchase_amount' => 0,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'applicable_to' => PromotionApplicabilityType::SPECIFIC_PRODUCTS,
            'show_on_website' => true,
            'show_in_pos' => true,
            'is_active' => true,
            'auto_apply' => true,
            'applicability' => ['products' => $breakfastProducts],
        ]);

        $promotionService->createPromotion([
            'name' => 'Buy 2 Get 1 Free — Beverages',
            'code' => 'BEV2FOR1',
            'description' => 'Buy any 2 beverages, get 1 free.',
            'promotion_type' => PromotionType::BUY_X_GET_Y,
            'buy_quantity' => 2,
            'get_quantity' => 1,
            'get_items_free' => true,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'applicable_to' => PromotionApplicabilityType::SPECIFIC_CATEGORIES,
            'show_on_website' => false,
            'show_in_pos' => true,
            'is_active' => true,
            'auto_apply' => false,
            'applicability' => ['categories' => [$beverages]],
        ]);

        $promotionService->createPromotion([
            'name' => 'Nike Brand Week',
            'code' => 'NIKE8',
            'description' => '8% off all Nike products.',
            'promotion_type' => PromotionType::PERCENTAGE_DISCOUNT,
            'discount_value' => 8,
            'min_purchase_amount' => 0,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
            'applicable_to' => PromotionApplicabilityType::SPECIFIC_BRANDS,
            'show_on_website' => true,
            'show_in_pos' => true,
            'is_active' => true,
            'auto_apply' => true,
            'applicability' => ['brands' => [$nike]],
        ]);

        $this->command->info('✓ Promotions: 4 coupons, 3 promotions');
    }
}
