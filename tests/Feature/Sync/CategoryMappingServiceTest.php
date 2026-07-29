<?php

namespace Tests\Feature\Sync;

use App\Models\TenantCategoryMapping;
use App\Services\Central\Sync\CategoryMappingService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CategoryMappingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('tenancy.database.central_connection', 'central');
        Config::set('database.connections.central.host', env('CENTRAL_DB_HOST', '127.0.0.1'));
        Config::set('database.connections.central.port', env('CENTRAL_DB_PORT', '3306'));
        Config::set('database.connections.central.database', env('CENTRAL_DB_DATABASE', 'poachy'));
        Config::set('database.connections.central.username', env('CENTRAL_DB_USERNAME', 'root'));
        Config::set('database.connections.central.password', env('CENTRAL_DB_PASSWORD', ''));
        Config::set('services.tenant_api.token', 'test-tenant-token');
        DB::purge('central');
        DB::connection('central')->statement('SET foreign_key_checks = 0');

        DB::connection('central')->table('tenants')->insertOrIgnore([
            'id' => 'category-mapping-test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('central')->table('domains')->insertOrIgnore([
            'domain' => 'category-mapping-test.poachy.test',
            'tenant_id' => 'category-mapping-test-tenant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::connection('central')->statement('SET foreign_key_checks = 0');
        TenantCategoryMapping::on('central')->where('tenant_id', 'category-mapping-test-tenant')->forceDelete();
        DB::connection('central')->table('domains')->where('tenant_id', 'category-mapping-test-tenant')->delete();
        DB::connection('central')->table('tenants')->where('id', 'category-mapping-test-tenant')->delete();
        DB::connection('central')->statement('SET foreign_key_checks = 1');
        parent::tearDown();
    }

    public function test_returns_only_categories_without_an_existing_mapping(): void
    {
        Http::fake([
            '*/api/v1/tenant/sync/inbound/categories' => Http::response([
                'data' => [
                    ['id' => 1, 'name' => 'Electronics', 'slug' => 'electronics'],
                    ['id' => 2, 'name' => 'Groceries', 'slug' => 'groceries'],
                ],
            ], 200),
        ]);

        TenantCategoryMapping::on('central')->create([
            'tenant_id' => 'category-mapping-test-tenant',
            'tenant_category_id' => 1,
            'tenant_category_name' => 'Electronics',
            'tenant_category_slug' => 'electronics',
            'marketplace_category_id' => 5,
            'confidence_score' => 100,
            'is_auto_mapped' => false,
            'is_verified' => true,
        ]);

        $unmapped = (new CategoryMappingService)->getUnmappedCategories('category-mapping-test-tenant');

        $this->assertCount(1, $unmapped);
        $this->assertSame(2, $unmapped->first()['id']);
    }

    public function test_returns_empty_collection_when_tenant_has_no_domain(): void
    {
        DB::connection('central')->table('domains')->where('tenant_id', 'category-mapping-test-tenant')->delete();

        $unmapped = (new CategoryMappingService)->getUnmappedCategories('category-mapping-test-tenant');

        $this->assertTrue($unmapped->isEmpty());
    }

    public function test_returns_empty_collection_when_tenant_request_fails(): void
    {
        Http::fake([
            '*/api/v1/tenant/sync/inbound/categories' => Http::response([], 500),
        ]);

        $unmapped = (new CategoryMappingService)->getUnmappedCategories('category-mapping-test-tenant');

        $this->assertTrue($unmapped->isEmpty());
    }

    public function test_returns_empty_collection_for_unknown_tenant(): void
    {
        $unmapped = (new CategoryMappingService)->getUnmappedCategories('nonexistent-tenant');

        $this->assertTrue($unmapped->isEmpty());
    }
}
