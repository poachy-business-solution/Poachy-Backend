<?php

namespace Tests\Feature\Tenant;

use App\Http\Controllers\Api\Tenant\Product\ProductController;
use App\Http\Controllers\Api\Tenant\Store\StoreController;
use App\Http\Controllers\Api\Tenant\User\TenantUserController;
use App\Services\Central\Admin\Tenant\TenantUserService;
use App\Services\Tenant\Product\ProductService;
use App\Services\Tenant\Product\ProductStockReceivingService;
use App\Services\Tenant\Store\StoreService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class MobilePaginationEnvelopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_tenant_user_index_uses_standard_pagination_envelope(): void
    {
        $userService = Mockery::mock(TenantUserService::class);
        $userService->shouldReceive('getAllUsers')
            ->once()
            ->with(15)
            ->andReturn($this->emptyPaginator());

        $payload = (new TenantUserController($userService))
            ->index(Request::create('/api/v1/tenant/users', 'GET'))
            ->getData(true);

        $this->assertStandardPaginationEnvelope($payload, 'Users retrieved successfully');
    }

    public function test_product_index_uses_standard_pagination_envelope(): void
    {
        $productService = Mockery::mock(ProductService::class);
        $productService->shouldReceive('list')
            ->once()
            ->with([], 15)
            ->andReturn($this->emptyPaginator());

        $payload = (new ProductController($productService, Mockery::mock(ProductStockReceivingService::class)))
            ->index(Request::create('/api/v1/tenant/products', 'GET'))
            ->getData(true);

        $this->assertStandardPaginationEnvelope($payload, 'Products retrieved successfully');
    }

    public function test_store_index_uses_standard_pagination_envelope(): void
    {
        $storeService = Mockery::mock(StoreService::class);
        $storeService->shouldReceive('getStores')
            ->once()
            ->with(Mockery::type('array'), 15)
            ->andReturn($this->emptyPaginator());

        $payload = (new StoreController($storeService))
            ->index(Request::create('/api/v1/tenant/stores', 'GET'))
            ->getData(true);

        $this->assertStandardPaginationEnvelope($payload, 'Stores retrieved successfully');
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            new Collection,
            total: 0,
            perPage: 15,
            currentPage: 1,
        );
    }

    private function assertStandardPaginationEnvelope(array $payload, string $message): void
    {
        $this->assertTrue($payload['success']);
        $this->assertSame($message, $payload['message']);

        $this->assertSame([], $payload['data']['data']);
        $this->assertSame([
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => 0,
            'from' => null,
            'to' => null,
        ], $payload['data']['pagination']);

        $this->assertArrayNotHasKey('products', $payload['data']);
        $this->assertArrayNotHasKey('links', $payload['data']);
        $this->assertArrayNotHasKey('meta', $payload['data']);
    }
}
