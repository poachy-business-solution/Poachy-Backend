<?php

namespace Tests\Feature\Billing;

use App\Services\Tenant\Billing\SaleDaysReporter;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Tenant side of trading-day billing: which branches sold on which Nairobi days.
 */
class SaleDaysReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'tenant');
        Config::set('database.connections.tenant.database', 'poachy_test');
        DB::purge('tenant');
        Schema::connection('tenant')->disableForeignKeyConstraints();
        Schema::connection('tenant')->dropIfExists('sales');
        Schema::connection('tenant')->dropIfExists('stores');
        Schema::connection('tenant')->create('stores', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->boolean('is_main_store')->default(false);
        });
        Schema::connection('tenant')->create('sales', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('store_id');
            $t->timestamp('sale_date');
            $t->softDeletes();
        });
        DB::table('stores')->insert([['id' => 1, 'name' => 'Main', 'is_main_store' => true], ['id' => 2, 'name' => 'Branch', 'is_main_store' => false]]);

        Config::set('services.central_api', ['url' => 'http://central.test', 'token' => 'sync-token', 'timeout' => 5]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Schema::connection('tenant')->dropIfExists('sales');
        Schema::connection('tenant')->dropIfExists('stores');
        Schema::connection('tenant')->enableForeignKeyConstraints();
        parent::tearDown();
    }

    private function sale(int $store, string $utc, bool $deleted = false): void
    {
        DB::table('sales')->insert(['store_id' => $store, 'sale_date' => $utc, 'deleted_at' => $deleted ? $utc : null]);
    }

    public function test_days_are_nairobi_days_and_cover_the_look_back_window(): void
    {
        $now = Carbon::parse('2026-10-12 08:00:00', 'UTC'); // 11:00 in Nairobi

        $this->sale(1, '2026-10-11 20:59:00'); // 23:59 Nairobi on the 11th
        $this->sale(1, '2026-10-11 21:00:00'); // 00:00 Nairobi on the 12th
        $this->sale(1, '2026-10-12 07:00:00');
        $this->sale(2, '2026-10-05 10:00:00'); // offline sale 7 days back, synced late: still in
        $this->sale(2, '2026-10-04 20:00:00'); // 23:00 Nairobi on the 4th: outside the window
        $this->sale(2, '2026-10-12 06:00:00', deleted: true);

        $days = (new SaleDaysReporter)->collect($now);

        $this->assertSame([
            ['store_id' => 2, 'date' => '2026-10-05', 'sales_count' => 1, 'is_main_branch' => false],
            ['store_id' => 1, 'date' => '2026-10-11', 'sales_count' => 1, 'is_main_branch' => true],
            ['store_id' => 1, 'date' => '2026-10-12', 'sales_count' => 2, 'is_main_branch' => true],
        ], $days);
    }

    public function test_no_sales_reports_an_empty_list(): void
    {
        $this->assertSame([], (new SaleDaysReporter)->collect(Carbon::parse('2026-10-12 08:00:00', 'UTC')));
    }

    public function test_the_report_is_sent_to_central_with_the_sync_token(): void
    {
        $this->sale(1, '2026-10-12 07:00:00');
        Http::fake(['central.test/*' => Http::response(['success' => true, 'data' => ['accepted' => 1]])]);

        $accepted = (new SaleDaysReporter)->report('shop-1', Carbon::parse('2026-10-12 08:00:00', 'UTC'));

        $this->assertSame(1, $accepted);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'http://central.test/api/v1/central/sync/inbound/sale-days'
            && $r->hasHeader('Authorization', 'Bearer sync-token')
            && $r->hasHeader('X-Tenant-ID', 'shop-1')
            && $r['tenant_id'] === 'shop-1'
            && $r['days'] === [['store_id' => 1, 'date' => '2026-10-12', 'sales_count' => 1, 'is_main_branch' => true]]);
    }

    public function test_a_refused_report_throws_so_the_next_run_resends(): void
    {
        Http::fake(['central.test/*' => Http::response(['success' => false, 'message' => 'nope'], 422)]);

        $this->expectException(RuntimeException::class);

        (new SaleDaysReporter)->report('shop-1');
    }
}
