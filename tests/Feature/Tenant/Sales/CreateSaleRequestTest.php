<?php

namespace Tests\Feature\Tenant\Sales;

use App\Http\Requests\Tenant\Sales\CreateSaleRequest;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class CreateSaleRequestTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantAuthorization();
        parent::tearDown();
    }

    private function requestAs($user): CreateSaleRequest
    {
        $request = new CreateSaleRequest;
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_authorize_allows_user_with_create_sales(): void
    {
        $user = $this->makeTenantUserWithPermission('create-sales');

        $this->assertTrue($this->requestAs($user)->authorize());
    }

    public function test_authorize_denies_user_without_create_sales(): void
    {
        $user = $this->makeTenantUserWithPermission('view-sales');

        $this->assertFalse($this->requestAs($user)->authorize());
    }
}
