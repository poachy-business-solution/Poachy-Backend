<?php

namespace Tests\Feature\Expenses;

use App\Enums\Tenant\RecurrenceFrequency;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\Store;
use App\Services\Tenant\Expenses\RecurrenceSchedule;
use App\Services\Tenant\Reports\OwnerOverviewService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** Repeating costs: dates, recording each due date once, stopping, and reports counting them once. */
class RecurringExpensesTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_monthly_dates_keep_their_day_across_short_months(): void
    {
        $start = CarbonImmutable::parse('2027-01-31');
        $dates = array_map(fn ($n) => RecurrenceSchedule::nth($start, RecurrenceFrequency::MONTHLY, 1, $n)->toDateString(), range(0, 4));

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30', '2027-05-31'], $dates);
        $this->assertSame('2028-02-29', RecurrenceSchedule::nth(CarbonImmutable::parse('2028-01-31'), RecurrenceFrequency::MONTHLY, 1, 1)->toDateString());
        $this->assertSame('2027-03-31', RecurrenceSchedule::nextAfter($start, RecurrenceFrequency::MONTHLY, 1, CarbonImmutable::parse('2027-02-28'))->toDateString());
    }

    public function test_a_repeating_cost_is_recorded_on_each_due_date_once_and_can_be_stopped(): void
    {
        $this->configureTenantHttpDatabases();
        $domain = 'recurring-'.strtolower(uniqid()).'.poachy.test';
        $tenant = $this->createTenantHttpFixture($domain);
        $url = fn (string $p) => $this->tenantUrl($domain, $p);

        try {
            $tenant->run(function () use ($url) {
                $store = Store::query()->create(['name' => 'Main', 'address' => 'Nairobi', 'city' => 'Nairobi', 'phone' => '+254700000001', 'email' => 'main@rec.test', 'is_main_store' => true, 'is_active' => true]);
                $rent = ExpenseCategory::query()->firstOrCreate(['code' => 'RENT'], ['name' => 'Rent', 'is_active' => true, 'is_recurring_eligible' => true]);
                $today = CarbonImmutable::parse(app(TillService::class)->localToday());
                $body = ['store_id' => $store->id, 'category_id' => $rent->id, 'amount' => 15000, 'frequency' => 'monthly', 'start_date' => $today->toDateString(), 'note' => 'Shop rent'];

                $this->actingAsTenant($this->createTenantUserWithRole('cashier'));
                $this->postJson($url('/api/v1/tenant/recurring-expenses'), $body)->assertForbidden();

                $this->actingAsTenant($this->createTenantUserWithRole('owner'));
                $this->postJson($url('/api/v1/tenant/recurring-expenses'), ['frequency' => 'hourly'] + $body)->assertUnprocessable()->assertJsonValidationErrors('frequency');
                $made = $this->postJson($url('/api/v1/tenant/recurring-expenses'), $body)->assertCreated()
                    ->assertJsonPath('data.status', 'active')
                    ->assertJsonPath('data.next_date', RecurrenceSchedule::nth($today, RecurrenceFrequency::MONTHLY, 1, 1)->toDateString());
                $id = $made->json('data.id');

                // The cost itself is today's entry; later dates are recorded as they fall due.
                $this->assertSame($today->toDateString(), Expense::query()->findOrFail($id)->expense_date->toDateString());
                $this->assertSame(0, Expense::query()->where('parent_expense_id', $id)->count());

                $this->getJson($url('/api/v1/tenant/recurring-expenses'))->assertOk()
                    ->assertJsonPath('data.items.0.id', $id)->assertJsonPath('data.monthly_total', 15000);

                // The daily job: nothing new today, one more next month, catches up two missed months, never twice.
                $tenantId = (string) tenant('id');
                $this->artisan('expenses:record-recurring', ['--tenant' => $tenantId, '--as-of' => $today->toDateString()])->expectsOutputToContain('recorded: 0');
                $this->artisan('expenses:record-recurring', ['--tenant' => $tenantId, '--as-of' => $today->addMonthNoOverflow()->toDateString()])->expectsOutputToContain('recorded: 1');
                $this->artisan('expenses:record-recurring', ['--tenant' => $tenantId, '--as-of' => $today->addMonthsNoOverflow(3)->toDateString()])->expectsOutputToContain('recorded: 2');
                $this->artisan('expenses:record-recurring', ['--tenant' => $tenantId, '--as-of' => $today->addMonthsNoOverflow(3)->toDateString()])->expectsOutputToContain('recorded: 0');
                $this->assertSame(3, Expense::query()->where('parent_expense_id', $id)->count());

                // Reports count the bill once (spread over the days), never its recorded payments.
                $money = app(OwnerOverviewService::class)->money($today, $today, null);
                $this->assertEqualsWithDelta(15000 / 30.4375, $money['spread_bills'], 0.05);
                $this->assertSame(0.0, (float) $money['one_off_expenses']);

                // Raise the rent, then stop it.
                $this->patchJson($url("/api/v1/tenant/recurring-expenses/{$id}"), ['amount' => 17000])->assertOk()->assertJsonPath('data.amount', 17000);
                $this->postJson($url("/api/v1/tenant/recurring-expenses/{$id}/stop"))->assertOk()->assertJsonPath('data.status', 'ended')->assertJsonPath('data.next_date', null);
                $this->artisan('expenses:record-recurring', ['--tenant' => $tenantId, '--as-of' => $today->addMonthsNoOverflow(8)->toDateString()])->expectsOutputToContain('recorded: 0');
                $this->getJson($url('/api/v1/tenant/recurring-expenses'))->assertJsonPath('data.monthly_total', 0);

                // Daily costs show their monthly equivalent.
                $daily = $this->postJson($url('/api/v1/tenant/recurring-expenses'), ['amount' => 300, 'frequency' => 'daily', 'start_date' => $today->addDay()->toDateString()] + $body)->assertCreated();
                $this->assertEqualsWithDelta(9131.25, $daily->json('data.per_month'), 0.01);
                $this->assertSame(0, Expense::query()->where('parent_expense_id', $daily->json('data.id'))->count()); // starts tomorrow

                // No note → the bill is described by its category, never a null description.
                $noNote = $this->postJson($url('/api/v1/tenant/recurring-expenses'), ['amount' => 500, 'frequency' => 'weekly', 'note' => null] + $body)->assertCreated();
                $this->assertSame('Rent', Expense::query()->findOrFail($noNote->json('data.id'))->description);

                $this->postJson($url('/api/v1/tenant/recurring-expenses/999999/stop'))->assertNotFound();
            });
        } finally {
            $this->deleteTenantHttpFixture($domain);
        }
    }
}
