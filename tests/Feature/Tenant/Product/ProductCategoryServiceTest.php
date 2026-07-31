<?php

namespace Tests\Feature\Tenant\Product;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductCategory;
use App\Repositories\Tenant\ProductCategoryRepository;
use App\Services\Tenant\Product\ProductCategoryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Tests\TestCase;

class ProductCategoryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        DB::connection('tenant')->statement('SET foreign_key_checks = 0');

        $this->createMinimalSchema();

        $fakeTenant = new \stdClass;
        $fakeTenant->id = 'test-tenant';
        app()->instance(TenantContract::class, $fakeTenant);

        Cache::tags(['tenant', 'test-tenant', 'product_categories'])->flush();
    }

    protected function tearDown(): void
    {
        $this->dropTestTables();
        DB::connection('tenant')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    private function makeService(): ProductCategoryService
    {
        return new ProductCategoryService(new ProductCategoryRepository);
    }

    private function createCategory(array $overrides = []): ProductCategory
    {
        return ProductCategory::create(array_merge([
            'name' => 'Test Category '.uniqid(),
        ], $overrides));
    }

    // =========================================================================
    // getAllCategories()
    // =========================================================================

    public function test_get_all_categories_filters_by_active_status(): void
    {
        $this->createCategory(['is_active' => true]);
        $this->createCategory(['is_active' => false]);

        $result = $this->makeService()->getAllCategories(['is_active' => true]);

        $this->assertCount(1, $result);
    }

    public function test_get_all_categories_filters_root_only_when_parent_id_is_string_null(): void
    {
        // The repository only special-cases the literal string 'null' (matching a raw
        // "?parent_id=null" query string) — a real PHP null is stripped by both
        // IndexProductCategoryRequest::getFilters()'s array_filter and this repository's
        // own isset() check before ever reaching the 'null' comparison, so passing an
        // actual null here (as opposed to getRootCategories()) is not a real call path.
        $root = $this->createCategory();
        $this->createCategory(['parent_id' => $root->id]);

        $result = $this->makeService()->getAllCategories(['parent_id' => 'null']);

        $this->assertCount(1, $result);
        $this->assertSame($root->id, $result->first()->id);
    }

    public function test_get_all_categories_filters_by_search(): void
    {
        $this->createCategory(['name' => 'Electronics']);
        $this->createCategory(['name' => 'Groceries']);

        $result = $this->makeService()->getAllCategories(['search' => 'Elect']);

        $this->assertCount(1, $result);
    }

    public function test_get_all_categories_with_products_loads_relation(): void
    {
        $category = $this->createCategory();
        Product::create([
            'name' => 'Cat Product', 'slug' => 'cat-product-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id, 'base_uom_id' => 1, 'is_active' => true,
        ]);

        $result = $this->makeService()->getAllCategoriesWithProducts();

        $this->assertTrue($result->first()->relationLoaded('products'));
    }

    // =========================================================================
    // getCategoryById() / getCategoryBySlug()
    // =========================================================================

    public function test_get_category_by_id_with_relations_loads_parent_and_children(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory(['parent_id' => $root->id]);

        $found = $this->makeService()->getCategoryById($child->id, withRelations: true);

        $this->assertTrue($found->relationLoaded('parent'));
        $this->assertSame($root->id, $found->parent->id);
    }

    public function test_get_category_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->makeService()->getCategoryById(999999));
    }

    public function test_get_category_by_slug_returns_matching_category(): void
    {
        $category = $this->createCategory(['slug' => 'my-category']);

        $found = $this->makeService()->getCategoryBySlug('my-category');

        $this->assertSame($category->id, $found->id);
    }

    // =========================================================================
    // createCategory()
    // =========================================================================

    public function test_create_category_generates_slug_when_not_provided(): void
    {
        $category = $this->makeService()->createCategory(['name' => 'Home Appliances']);

        $this->assertSame('home-appliances', $category->slug);
    }

    public function test_create_category_throws_when_explicit_slug_taken(): void
    {
        $this->createCategory(['slug' => 'taken-slug']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->createCategory(['name' => 'Another', 'slug' => 'taken-slug']);
    }

    public function test_create_category_throws_when_parent_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->createCategory(['name' => 'Orphan', 'parent_id' => 999999]);
    }

    public function test_create_category_succeeds_with_valid_parent(): void
    {
        $parent = $this->createCategory();

        $child = $this->makeService()->createCategory(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertSame($parent->id, $child->parent_id);
    }

    // =========================================================================
    // updateCategory()
    // =========================================================================

    public function test_update_category_throws_for_unknown_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateCategory(999999, ['name' => 'X']);
    }

    public function test_update_category_throws_when_new_slug_collides(): void
    {
        $this->createCategory(['slug' => 'taken-slug']);
        $category = $this->createCategory(['slug' => 'my-slug']);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateCategory($category->id, ['slug' => 'taken-slug']);
    }

    public function test_update_category_throws_when_setting_self_as_parent(): void
    {
        $category = $this->createCategory();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateCategory($category->id, ['parent_id' => $category->id]);
    }

    public function test_update_category_throws_when_parent_not_found(): void
    {
        $category = $this->createCategory();

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->updateCategory($category->id, ['parent_id' => 999999]);
    }

    public function test_update_category_throws_on_circular_reference(): void
    {
        $grandparent = $this->createCategory();
        $parent = $this->createCategory(['parent_id' => $grandparent->id]);

        $this->expectException(\InvalidArgumentException::class);

        // Trying to make grandparent a child of its own descendant (parent)
        $this->makeService()->updateCategory($grandparent->id, ['parent_id' => $parent->id]);
    }

    public function test_update_category_succeeds_with_valid_data(): void
    {
        $category = $this->createCategory(['name' => 'Old Name']);

        $updated = $this->makeService()->updateCategory($category->id, ['name' => 'New Name']);

        $this->assertSame('New Name', $updated->name);
    }

    // =========================================================================
    // activateCategory() / deactivateCategory()
    // =========================================================================

    public function test_activate_and_deactivate_category(): void
    {
        $category = $this->createCategory(['is_active' => false]);
        $service = $this->makeService();

        $activated = $service->activateCategory($category->id);
        $this->assertTrue($activated->is_active);

        $deactivated = $service->deactivateCategory($category->id);
        $this->assertFalse($deactivated->is_active);
    }

    // =========================================================================
    // deleteCategory()
    // =========================================================================

    public function test_delete_category_throws_when_it_has_products(): void
    {
        $category = $this->createCategory();
        Product::create([
            'name' => 'Cat Product', 'slug' => 'cat-product-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'category_id' => $category->id, 'base_uom_id' => 1,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->deleteCategory($category->id);
    }

    public function test_delete_category_throws_when_it_has_children(): void
    {
        $parent = $this->createCategory();
        $this->createCategory(['parent_id' => $parent->id]);

        $this->expectException(\InvalidArgumentException::class);

        $this->makeService()->deleteCategory($parent->id);
    }

    public function test_delete_category_succeeds_when_no_products_or_children(): void
    {
        $category = $this->createCategory();

        $result = $this->makeService()->deleteCategory($category->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id], connection: 'tenant');
    }

    // =========================================================================
    // getRootCategories() / getCategoryChildren()
    // =========================================================================

    public function test_get_root_categories_excludes_children(): void
    {
        $root = $this->createCategory();
        $this->createCategory(['parent_id' => $root->id]);

        $result = $this->makeService()->getRootCategories();

        $this->assertCount(1, $result);
        $this->assertSame($root->id, $result->first()->id);
    }

    public function test_get_root_categories_active_only_filter(): void
    {
        $this->createCategory(['is_active' => true]);
        $this->createCategory(['is_active' => false]);

        $result = $this->makeService()->getRootCategories(activeOnly: true);

        $this->assertCount(1, $result);
    }

    public function test_get_category_children_returns_only_direct_children(): void
    {
        $root = $this->createCategory();
        $child = $this->createCategory(['parent_id' => $root->id]);
        $this->createCategory(['parent_id' => $child->id]); // grandchild

        $result = $this->makeService()->getCategoryChildren($root->id);

        $this->assertCount(1, $result);
        $this->assertSame($child->id, $result->first()->id);
    }

    // =========================================================================
    // Schema helpers
    // =========================================================================

    private function dropTestTables(): void
    {
        foreach (['products', 'product_categories', 'units_of_measure'] as $table) {
            Schema::connection('tenant')->dropIfExists($table);
        }
    }

    private function createMinimalSchema(): void
    {
        $conn = 'tenant';

        Schema::connection($conn)->create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->timestamps();
        });

        DB::connection($conn)->table('units_of_measure')->insert([
            'id' => 1, 'code' => 'pcs', 'name' => 'Piece', 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::connection($conn)->create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection($conn)->create('products', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->unique();
            $table->string('product_type')->default('simple');
            $table->string('description')->nullable();
            $table->string('stock_status')->default('in_stock');
            $table->boolean('is_weighed')->default(false);
            $table->boolean('requires_batch_tracking')->default(false);
            $table->boolean('requires_serial_tracking')->default(false);
            $table->decimal('base_selling_price', 15, 2)->default(0);
            $table->string('primary_image')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('base_uom_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_available_online')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
