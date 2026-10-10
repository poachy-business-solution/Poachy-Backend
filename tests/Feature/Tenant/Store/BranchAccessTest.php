<?php

namespace Tests\Feature\Tenant\Store;

use App\Http\Resources\Tenant\Auth\TenantUserResource;
use App\Models\Tenant\Store;
use App\Rules\Tenant\AssignedStore;
use App\Services\Tenant\Store\BranchLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantAuthorization;
use Tests\TestCase;

class BranchAccessTest extends TestCase
{
    use InteractsWithTenantAuthorization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTenantAuthorization();

        DB::connection('tenant')->table('stores')->insert([
            ['id' => 1, 'name' => 'CBD', 'is_active' => true, 'is_main_store' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Westlands', 'is_active' => true, 'is_main_store' => false, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'name' => 'Closed', 'is_active' => false, 'is_main_store' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenantAuthorization();
        parent::tearDown();
    }

    public function test_owners_and_managers_work_at_every_open_branch(): void
    {
        $this->assertSame([1, 2], $this->makeTenantUserWithRole('owner')->workableStoreIds());
        $this->assertSame([1, 2], $this->makeTenantUserWithRole('manager')->workableStoreIds());
    }

    public function test_unassigned_staff_work_at_the_main_branch_only(): void
    {
        $cashier = $this->makeTenantUserWithRole('cashier');

        $this->assertSame([1], $cashier->workableStoreIds());
        $this->assertTrue($cashier->canWorkAt(1));
        $this->assertFalse($cashier->canWorkAt(2));
    }

    public function test_assigned_staff_work_only_at_their_open_branches(): void
    {
        $cashier = $this->makeTenantUserWithRole('cashier');
        $cashier->stores()->sync([2, 3]);

        $this->assertSame([2], $cashier->workableStoreIds());
        $this->assertFalse($cashier->canWorkAt(1));
    }

    public function test_a_cashier_cannot_act_as_another_branch(): void
    {
        $cashier = $this->makeTenantUserWithRole('cashier');
        Auth::setUser($cashier);

        $own = Validator::make(['store_id' => 1], ['store_id' => [new AssignedStore]]);
        $other = Validator::make(['store_id' => 2], ['store_id' => [new AssignedStore]]);

        $this->assertTrue($own->passes());
        $this->assertTrue($other->fails());
        $this->assertStringContainsString('Westlands', $other->errors()->first('store_id'));
    }

    public function test_the_user_payload_lists_the_branches_they_can_work_at(): void
    {
        $cashier = $this->makeTenantUserWithRole('cashier');
        $cashier->stores()->sync([2]);

        $payload = (new TenantUserResource($cashier))->toArray(new Request);

        $this->assertSame([2], $payload['assigned_store_ids']);
    }

    public function test_branch_limit_blocks_one_branch_too_many(): void
    {
        $limits = $this->limitsOf(2);

        try {
            $limits->ensureCanAddActiveBranch();
            $this->fail('Expected the plan limit to block a third active branch.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Your plan allows 2 branches', $e->errors()['store'][0]);
        }

        $limits->ensureCanAddActiveBranch(Store::find(2));
        $this->limitsOf(3)->ensureCanAddActiveBranch();
        $this->limitsOf(null)->ensureCanAddActiveBranch();
        $this->addToAssertionCount(3);
    }

    public function test_without_a_subscription_there_is_no_limit(): void
    {
        $this->assertNull((new BranchLimitService)->maxBranches());
    }

    private function limitsOf(?int $limit): BranchLimitService
    {
        return new class($limit) extends BranchLimitService
        {
            public function __construct(private ?int $limit) {}

            public function maxBranches(): ?int
            {
                return $this->limit;
            }
        };
    }
}
