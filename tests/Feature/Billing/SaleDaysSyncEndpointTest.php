<?php

namespace Tests\Feature\Billing;

use App\Models\TenantSaleDay;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Central side: POST /api/v1/central/sync/inbound/sale-days.
 */
class SaleDaysSyncEndpointTest extends TestCase
{
    private const URL = '/api/v1/central/sync/inbound/sale-days';

    private const TENANT = 'sale-days-tenant';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'central'); // Sanctum's bearer lookup uses the default connection
        Config::set('tenancy.database.central_connection', 'central');
        Config::set('services.central_api.token', 'sync-token');
        DB::connection('central')->beginTransaction();
        DB::connection('central')->table('tenants')->insert(['id' => self::TENANT, 'created_at' => now(), 'updated_at' => now()]);
        $this->travelTo('2026-10-12 08:00:00');
    }

    protected function tearDown(): void
    {
        DB::connection('central')->rollBack();
        parent::tearDown();
    }

    /**
     * @param  array<int, array<string, mixed>>  $days
     */
    private function report(array $days, string $token = 'sync-token', string $headerTenant = self::TENANT, string $tenant = self::TENANT)
    {
        return $this->withToken($token)
            ->withHeader('X-Tenant-ID', $headerTenant)
            ->postJson(self::URL, ['tenant_id' => $tenant, 'days' => $days]);
    }

    private function day(int $store, string $date, int $count = 1, bool $main = true): array
    {
        return ['store_id' => $store, 'date' => $date, 'sales_count' => $count, 'is_main_branch' => $main];
    }

    public function test_a_report_is_stored_and_re_reports_update_in_place(): void
    {
        $this->report([$this->day(1, '2026-10-11', 3), $this->day(2, '2026-10-11', 1, false)])
            ->assertOk()->assertJsonPath('data.accepted', 2);

        // An hour later a late offline sale has synced: same day, higher count.
        $this->report([$this->day(1, '2026-10-11', 4), $this->day(2, '2026-10-11', 1, false)])->assertOk();

        $rows = TenantSaleDay::query()->where('tenant_id', self::TENANT)->orderBy('store_id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame(4, $rows[0]->sales_count);
        $this->assertTrue($rows[0]->is_main_branch);
        $this->assertFalse($rows[1]->is_main_branch);
    }

    public function test_an_empty_report_is_fine(): void
    {
        $this->report([])->assertOk()->assertJsonPath('data.accepted', 0);
    }

    public function test_a_wrong_or_missing_token_is_refused(): void
    {
        $this->report([$this->day(1, '2026-10-11')], 'wrong')->assertForbidden();
        $this->postJson(self::URL, ['tenant_id' => self::TENANT, 'days' => []])->assertForbidden();

        Config::set('services.central_api.token', null); // unset on the server must not mean "open"
        $this->report([], '')->assertForbidden();

        $this->assertSame(0, TenantSaleDay::query()->where('tenant_id', self::TENANT)->count());
    }

    public function test_a_shop_cannot_report_for_another_shop(): void
    {
        $this->report([$this->day(1, '2026-10-11')], headerTenant: 'someone-else')->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    }

    public function test_an_unknown_shop_is_refused(): void
    {
        $this->report([], headerTenant: 'ghost', tenant: 'ghost')->assertUnprocessable()->assertJsonValidationErrors('tenant_id');
    }

    public function test_days_outside_the_window_or_malformed_are_refused(): void
    {
        $this->report([$this->day(1, '2026-10-01')])->assertUnprocessable()->assertJsonValidationErrors('days.0.date');
        $this->report([$this->day(1, '2026-10-20')])->assertUnprocessable()->assertJsonValidationErrors('days.0.date');
        $this->report([$this->day(1, '2026-10-11', 0)])->assertUnprocessable()->assertJsonValidationErrors('days.0.sales_count');
        $this->report([['store_id' => 1]])->assertUnprocessable();
    }
}
