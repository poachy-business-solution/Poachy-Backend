<?php

namespace Tests\Feature\Tenant\Product;

use App\Http\Resources\Tenant\Product\ProductBrandResource;
use App\Http\Resources\Tenant\Product\ProductBundleResource;
use App\Http\Resources\Tenant\Product\ProductListResource;
use App\Http\Resources\Tenant\Product\ProductMinimalResource;
use App\Http\Resources\Tenant\Product\ProductResource;
use App\Models\Tenant as CentralTenant;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductBrand;
use App\Models\Tenant\ProductBundle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Tests\TestCase;

class ProductMediaUrlResourceTest extends TestCase
{
    private ?string $assetTenantStoragePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.url', 'http://central.test');
        URL::forceRootUrl('http://techhaven.localhost');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->assetTenantStoragePath) {
            File::deleteDirectory($this->assetTenantStoragePath);
        }

        URL::forceRootUrl(null);
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_product_resources_return_tenant_asset_urls(): void
    {
        $request = Request::create('http://techhaven.localhost/api/v1/tenant/products', 'GET');
        $product = new Product([
            'name' => 'A54',
            'slug' => 'a54',
            'sku' => 'A54',
            'base_selling_price' => 100,
            'primary_image' => 'products/images/primary.jpg',
            'secondary_images' => [
                'products/images/secondary.jpg',
                'storage/products/images/legacy.jpg',
            ],
        ]);

        $fullPayload = (new ProductResource($product))->toArray($request);
        $listPayload = (new ProductListResource($product))->toArray($request);
        $minimalPayload = (new ProductMinimalResource($product))->toArray($request);

        $this->assertSame(
            'http://techhaven.localhost/tenancy/assets/products/images/primary.jpg',
            $fullPayload['primary_image']
        );
        $this->assertSame($fullPayload['primary_image'], $listPayload['primary_image']);
        $this->assertSame($fullPayload['primary_image'], $minimalPayload['primary_image']);
        $this->assertSame([
            'http://techhaven.localhost/tenancy/assets/products/images/secondary.jpg',
            'http://techhaven.localhost/tenancy/assets/products/images/legacy.jpg',
        ], $fullPayload['secondary_images']);
    }

    public function test_product_brand_and_bundle_resources_return_tenant_asset_urls(): void
    {
        $request = Request::create('http://techhaven.localhost/api/v1/tenant/products', 'GET');
        $brand = new ProductBrand([
            'name' => 'Tech Haven',
            'slug' => 'tech-haven',
            'logo_url' => 'products/brands/logos/logo.jpg',
        ]);
        $bundle = new ProductBundle([
            'images' => ['bundles/images/combo.jpg'],
        ]);

        $brandPayload = (new ProductBrandResource($brand))->toArray($request);
        $bundleImages = (new class($bundle) extends ProductBundleResource
        {
            public function exposeFormatImages(array $images): array
            {
                return $this->formatImages($images);
            }
        })->exposeFormatImages($bundle->images);

        $this->assertSame(
            'http://techhaven.localhost/tenancy/assets/products/brands/logos/logo.jpg',
            $brandPayload['logo_url']
        );
        $this->assertSame(
            'http://techhaven.localhost/tenancy/assets/bundles/images/combo.jpg',
            $bundleImages[0]['url']
        );
    }

    public function test_tenant_asset_route_serves_files_from_suffixed_tenant_storage(): void
    {
        $tenantId = 'asset-test-tenant';
        $tenant = new CentralTenant;
        $tenant->id = $tenantId;
        $tenant->exists = true;

        $this->mock(DomainTenantResolver::class, function (MockInterface $mock) use ($tenant) {
            $mock->shouldReceive('resolve')
                ->once()
                ->with('techhaven.localhost')
                ->andReturn($tenant);
        });

        $this->assetTenantStoragePath = storage_path('tenant'.$tenantId);
        $assetPath = $this->assetTenantStoragePath.'/app/public/products/images/primary.txt';

        File::ensureDirectoryExists(dirname($assetPath));
        File::put($assetPath, 'tenant asset content');

        $response = $this->get('http://techhaven.localhost/tenancy/assets/products/images/primary.txt');

        $response->assertOk();
    }
}
