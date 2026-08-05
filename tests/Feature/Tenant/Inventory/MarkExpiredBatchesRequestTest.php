<?php

namespace Tests\Feature\Tenant\Inventory;

use App\Http\Requests\Tenant\Inventory\Batch\MarkExpiredBatchesRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class MarkExpiredBatchesRequestTest extends TestCase
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

    private function requestAs($user): MarkExpiredBatchesRequest
    {
        $request = new MarkExpiredBatchesRequest;
        $request->setUserResolver(fn () => $user);

        return $request;
    }

    public function test_authorize_allows_user_with_manage_inventory(): void
    {
        $user = $this->makeTenantUserWithPermission('manage-inventory');

        $this->assertTrue($this->requestAs($user)->authorize());
    }

    public function test_authorize_denies_user_without_manage_inventory(): void
    {
        $user = $this->makeTenantUserWithPermission('view-inventory');

        $this->assertFalse($this->requestAs($user)->authorize());
    }

    public function test_store_id_is_required(): void
    {
        $rules = (new MarkExpiredBatchesRequest)->rules();

        $validator = Validator::make([], $rules);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('store_id', $validator->errors()->toArray());
    }
}
