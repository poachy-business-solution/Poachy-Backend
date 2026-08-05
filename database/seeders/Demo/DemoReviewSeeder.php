<?php

namespace Database\Seeders\Demo;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductReview;
use App\Models\Tenant\TenantDeliveryZone;
use App\Services\Tenant\Business\DeliveryZoneService;
use App\Services\Tenant\ReviewResponseService;
use Illuminate\Database\Seeder;

class DemoReviewSeeder extends Seeder
{
    protected const REVIEWS = [
        ['product' => 'Samsung Galaxy A14', 'customer' => 'Wanjiru Kamau', 'rating' => 5, 'title' => 'Great value phone', 'text' => 'Battery lasts all day and the camera is solid for the price.', 'respond' => true],
        ['product' => 'Samsung Galaxy A14', 'customer' => 'Otieno Ochieng', 'rating' => 4, 'title' => 'Good but a bit slow', 'text' => 'Decent phone, gets a little laggy with too many apps open.', 'respond' => false],
        ['product' => 'Tecno Spark 10', 'customer' => 'Chebet Kiplagat', 'rating' => 4, 'title' => 'Solid budget pick', 'text' => 'Does everything I need for the price point.', 'respond' => false],
        ['product' => 'Nike Air Max Sneakers', 'customer' => 'Njeri Maina', 'rating' => 5, 'title' => 'Comfortable and stylish', 'text' => 'True to size and very comfortable for daily wear.', 'respond' => true],
        ['product' => 'Adidas Sport T-Shirt', 'customer' => 'Mumbi Njoroge', 'rating' => 3, 'title' => 'Fabric could be better', 'text' => 'Fit is nice but the material feels thinner than expected.', 'respond' => true],
        ['product' => 'Ramtons Electric Kettle', 'customer' => 'Kiprotich Rono', 'rating' => 5, 'title' => 'Boils fast', 'text' => 'Heats up quickly and looks great on the counter.', 'respond' => false],
        ['product' => 'LG Microwave 20L', 'customer' => 'Achieng Odhiambo', 'rating' => 4, 'title' => 'Does the job', 'text' => 'Compact and reliable, delivery was quick too.', 'respond' => false],
        ['product' => 'Nestlé Cerelac 400g', 'customer' => 'Too Sang', 'rating' => 5, 'title' => 'Baby loves it', 'text' => 'Always fresh and well packaged on arrival.', 'respond' => false],
    ];

    public function run(ReviewResponseService $reviewResponseService, DeliveryZoneService $deliveryZoneService): void
    {
        foreach (self::REVIEWS as $index => $spec) {
            $product = Product::where('name', $spec['product'])->first();

            if (! $product) {
                continue;
            }

            $review = ProductReview::create([
                'central_review_id' => 80000 + $index,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'customer_name' => $spec['customer'],
                'rating' => $spec['rating'],
                'title' => $spec['title'],
                'review_text' => $spec['text'],
                'is_verified_purchase' => true,
                'status' => 'published',
                'reviewed_at' => now()->subDays(random_int(1, 20)),
            ]);

            if ($spec['respond']) {
                $reviewResponseService->createResponse(
                    $review,
                    'Thank you for the feedback — glad you\'re enjoying it! Reach out any time if you need help.'
                );
            }
        }

        $deliveryZoneService->store([
            'zone_name' => 'Nairobi CBD & Environs',
            'zone_type' => 'city',
            'cities' => ['Nairobi'],
            'counties' => ['Nairobi'],
            'standard_fee' => 150,
            'express_fee' => 350,
            'free_delivery_threshold' => 3000,
            'standard_delivery_time' => '1-2 business days',
            'express_delivery_time' => 'Same day',
            'supported_methods' => ['standard', 'express'],
            'priority' => 1,
            'is_active' => true,
        ]);

        $deliveryZoneService->store([
            'zone_name' => 'Kiambu County',
            'zone_type' => 'county',
            'cities' => [],
            'counties' => ['Kiambu'],
            'standard_fee' => 250,
            'express_fee' => 500,
            'free_delivery_threshold' => 5000,
            'standard_delivery_time' => '2-3 business days',
            'express_delivery_time' => 'Next day',
            'supported_methods' => ['standard', 'express'],
            'priority' => 2,
            'is_active' => true,
        ]);

        $deliveryZoneService->store([
            'zone_name' => 'Machakos County',
            'zone_type' => 'county',
            'cities' => [],
            'counties' => ['Machakos'],
            'standard_fee' => 300,
            'express_fee' => null,
            'free_delivery_threshold' => 6000,
            'standard_delivery_time' => '3-4 business days',
            'supported_methods' => ['standard'],
            'priority' => 3,
            'is_active' => true,
        ]);

        $this->command->info('✓ Reviews: '.count(self::REVIEWS).' reviews, '.TenantDeliveryZone::count().' delivery zones');
    }
}
