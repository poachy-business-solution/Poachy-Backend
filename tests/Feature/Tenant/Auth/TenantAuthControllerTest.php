<?php

namespace Tests\Feature\Tenant\Auth;

use App\Http\Controllers\Api\Tenant\Auth\TenantAuthController;
use App\Services\Tenant\Auth\TenantAuthService;
use Illuminate\Http\Request;
use Mockery;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class TenantAuthControllerTest extends TestCase
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
        Mockery::close();

        parent::tearDown();
    }

    public function test_me_response_includes_roles_for_token_restore(): void
    {
        $createdUser = $this->makeTenantUserWithRole('cashier');
        $user = $createdUser->fresh();

        $this->assertFalse($user->relationLoaded('roles'));

        $request = Request::create('/api/v1/tenant/auth/me', 'GET');
        $request->setUserResolver(fn () => $user);

        $controller = new TenantAuthController(Mockery::mock(TenantAuthService::class));
        $response = $controller->me($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['cashier'], $payload['data']['roles']);
    }
}
