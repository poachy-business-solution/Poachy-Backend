<?php

namespace Tests\Feature\Central\Admin;

use App\Models\SubscriptionPlan;
use App\Repositories\Central\SubscriptionPlanRepository;
use App\Services\Central\Shared\SubscriptionPlanService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubscriptionPlanServiceTest extends TestCase
{
    private array $createdPlanIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        DB::purge('central');
        DB::setDefaultConnection('central');
    }

    protected function tearDown(): void
    {
        SubscriptionPlan::on('central')->whereIn('id', $this->createdPlanIds)->forceDelete();

        parent::tearDown();
    }

    private function makeService(): SubscriptionPlanService
    {
        return new SubscriptionPlanService(new SubscriptionPlanRepository);
    }

    private function createPlan(array $overrides = []): SubscriptionPlan
    {
        $plan = SubscriptionPlan::on('central')->create(array_merge([
            'name' => 'Plan '.uniqid(),
            'slug' => 'plan-'.uniqid(),
            'price' => 1000,
            'billing_cycle_days' => 30,
            'is_active' => true,
            'is_featured' => false,
            'features' => ['max_products' => 50],
        ], $overrides));

        $this->createdPlanIds[] = $plan->id;

        return $plan;
    }

    // =========================================================================
    // listPlans()
    // =========================================================================

    public function test_list_plans_filters_by_active_status(): void
    {
        $active = $this->createPlan(['is_active' => true]);
        $inactive = $this->createPlan(['is_active' => false]);

        $result = $this->makeService()->listPlans(['is_active' => true]);

        $this->assertTrue($result->contains('id', $active->id));
        $this->assertFalse($result->contains('id', $inactive->id));
    }

    public function test_list_plans_filters_by_price_range(): void
    {
        $cheap = $this->createPlan(['price' => 500]);
        $expensive = $this->createPlan(['price' => 50000]);

        $result = $this->makeService()->listPlans(['min_price' => 1000, 'max_price' => 60000]);

        $this->assertFalse($result->contains('id', $cheap->id));
        $this->assertTrue($result->contains('id', $expensive->id));
    }

    public function test_list_plans_sorts_by_price_ascending_by_default(): void
    {
        $low = $this->createPlan(['price' => 111, 'slug' => 'sort-low-'.uniqid()]);
        $high = $this->createPlan(['price' => 999999, 'slug' => 'sort-high-'.uniqid()]);

        $result = $this->makeService()->listPlans();
        $ids = $result->pluck('id')->all();

        $this->assertLessThan(array_search($high->id, $ids), array_search($low->id, $ids));
    }

    // =========================================================================
    // getPlanById() / getPlanBySlug()
    // =========================================================================

    public function test_get_plan_by_id_returns_matching_plan(): void
    {
        $plan = $this->createPlan();

        $this->assertSame($plan->id, $this->makeService()->getPlanById($plan->id)->id);
    }

    public function test_get_plan_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getPlanById(999999999));
    }

    public function test_get_plan_by_slug_returns_matching_plan(): void
    {
        $plan = $this->createPlan(['slug' => 'unique-slug-'.uniqid()]);

        $this->assertSame($plan->id, $this->makeService()->getPlanBySlug($plan->slug)->id);
    }

    public function test_get_plan_by_slug_returns_null_for_unknown_slug(): void
    {
        $this->assertNull($this->makeService()->getPlanBySlug('does-not-exist-'.uniqid()));
    }

    // =========================================================================
    // getActivePlans() / getFeaturedPlans()
    // =========================================================================

    public function test_get_active_plans_excludes_inactive(): void
    {
        $active = $this->createPlan(['is_active' => true]);
        $inactive = $this->createPlan(['is_active' => false]);

        $result = $this->makeService()->getActivePlans();

        $this->assertTrue($result->contains('id', $active->id));
        $this->assertFalse($result->contains('id', $inactive->id));
    }

    public function test_get_featured_plans_requires_both_featured_and_active(): void
    {
        $featuredActive = $this->createPlan(['is_featured' => true, 'is_active' => true]);
        $featuredInactive = $this->createPlan(['is_featured' => true, 'is_active' => false]);
        $activeNotFeatured = $this->createPlan(['is_featured' => false, 'is_active' => true]);

        $result = $this->makeService()->getFeaturedPlans();

        $this->assertTrue($result->contains('id', $featuredActive->id));
        $this->assertFalse($result->contains('id', $featuredInactive->id));
        $this->assertFalse($result->contains('id', $activeNotFeatured->id));
    }

    // =========================================================================
    // isPlanFree()
    // =========================================================================

    public function test_is_plan_free_true_for_zero_price(): void
    {
        $plan = $this->createPlan(['price' => 0]);

        $this->assertTrue($this->makeService()->isPlanFree($plan->id));
    }

    public function test_is_plan_free_false_for_paid_plan(): void
    {
        $plan = $this->createPlan(['price' => 100]);

        $this->assertFalse($this->makeService()->isPlanFree($plan->id));
    }

    public function test_is_plan_free_false_for_unknown_plan(): void
    {
        $this->assertFalse($this->makeService()->isPlanFree(999999999));
    }

    // =========================================================================
    // getPlanFeature()
    // =========================================================================

    public function test_get_plan_feature_returns_value_when_present(): void
    {
        $plan = $this->createPlan(['features' => ['max_products' => 200]]);

        $this->assertSame(200, $this->makeService()->getPlanFeature($plan->id, 'max_products'));
    }

    public function test_get_plan_feature_returns_default_when_missing(): void
    {
        $plan = $this->createPlan(['features' => []]);

        $this->assertSame('fallback', $this->makeService()->getPlanFeature($plan->id, 'max_locations', 'fallback'));
    }

    public function test_get_plan_feature_returns_default_for_unknown_plan(): void
    {
        $this->assertSame('fallback', $this->makeService()->getPlanFeature(999999999, 'max_products', 'fallback'));
    }
}
