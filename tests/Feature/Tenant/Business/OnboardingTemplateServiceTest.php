<?php

namespace Tests\Feature\Tenant\Business;

use App\Models\Tenant\ProductCategory;
use App\Services\Tenant\Business\OnboardingTemplateService;
use Database\Seeders\ProductCategorySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OnboardingTemplateServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
    }

    protected function tearDown(): void
    {
        if (Schema::connection('tenant')->hasTable('product_categories')) {
            Schema::connection('tenant')->drop('product_categories');
        }

        Mockery::close();

        parent::tearDown();
    }

    public function test_pharmacy_template_excludes_generic_fashion_categories(): void
    {
        $template = $this->makeService()->forSlugs('pharmacy', 'retail-consumer-goods');

        $this->assertSame('pharmacy', $template['template_key']);
        $this->assertSame('business_category', $template['source']);
        $this->assertContains('Medicines', $this->categoryNames($template));
        $this->assertContains('Tablet', $this->unitNames($template));
        $this->assertContains('Strip', $this->unitNames($template));
        $this->assertNotContains('Menswear', $this->categoryNames($template));
        $this->assertNotContains('Mens Fashion', $this->categoryNames($template));
    }

    public function test_business_type_fallback_uses_food_template_for_restaurant_verticals(): void
    {
        $template = $this->makeService()->forSlugs(null, 'food-beverage');

        $this->assertSame('restaurant', $template['template_key']);
        $this->assertSame('business_type', $template['source']);
        $this->assertContains('Menu Items', $this->categoryNames($template));
        $this->assertContains('Portion', $this->unitNames($template));
    }

    public function test_unknown_selection_falls_back_to_general_retail(): void
    {
        $template = $this->makeService()->forSlugs('unknown-category', 'unknown-type');

        $this->assertSame('general-retail', $template['template_key']);
        $this->assertSame('default', $template['source']);
        $this->assertContains('Electronics', $this->categoryNames($template));
        $this->assertContains('Groceries', $this->categoryNames($template));
    }

    public function test_product_category_seeder_uses_template_and_is_idempotent(): void
    {
        $this->createProductCategoriesTable();

        $template = $this->makeService()->forSlugs('pharmacy', 'retail-consumer-goods');
        $mock = Mockery::mock(OnboardingTemplateService::class);
        $mock->shouldReceive('forCurrentTenant')->twice()->andReturn($template);
        $this->app->instance(OnboardingTemplateService::class, $mock);

        ProductCategory::withoutEvents(function () {
            (new ProductCategorySeeder)->run();
            (new ProductCategorySeeder)->run();
        });

        $this->assertSame(10, ProductCategory::count());
        $this->assertDatabaseHas('product_categories', ['slug' => 'medicines'], connection: 'tenant');
        $this->assertDatabaseHas('product_categories', ['slug' => 'pain-relief'], connection: 'tenant');
        $this->assertDatabaseMissing('product_categories', ['slug' => 'mens-fashion'], connection: 'tenant');
    }

    private function makeService(): OnboardingTemplateService
    {
        return new OnboardingTemplateService;
    }

    private function categoryNames(array $template): array
    {
        $names = [];

        foreach ($template['categories'] as $category) {
            $names[] = $category['name'];

            foreach ($category['children'] as $child) {
                $names[] = $child['name'];
            }
        }

        return $names;
    }

    private function unitNames(array $template): array
    {
        return array_column($template['units_of_measure'], 'name');
    }

    private function createProductCategoriesTable(): void
    {
        Schema::connection('tenant')->dropIfExists('product_categories');

        Schema::connection('tenant')->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
