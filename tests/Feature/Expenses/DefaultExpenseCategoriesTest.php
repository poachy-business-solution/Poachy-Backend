<?php

namespace Tests\Feature\Expenses;

use App\Models\Tenant\ExpenseCategory;
use Tests\Feature\Tenant\Concerns\InteractsWithTenantHttpAuth;
use Tests\TestCase;

/** Shops without expense categories get the defaults; shops with their own are left alone. */
class DefaultExpenseCategoriesTest extends TestCase
{
    use InteractsWithTenantHttpAuth;

    public function test_the_backfill_fills_empty_shops_only_and_is_safe_to_rerun(): void
    {
        $this->configureTenantHttpDatabases();
        $empty = $this->createTenantHttpFixture('exp-empty-'.strtolower(uniqid()).'.poachy.test');
        $own = $this->createTenantHttpFixture('exp-own-'.strtolower(uniqid()).'.poachy.test');

        try {
            $empty->run(fn () => ExpenseCategory::query()->delete());
            $own->run(function () {
                ExpenseCategory::query()->delete();
                ExpenseCategory::query()->create(['name' => 'Shoe polish', 'code' => 'SHOE_POLISH', 'is_active' => true]);
            });

            $this->artisan('tenants:seed-expense-categories', ['--tenant' => $empty->id])->assertSuccessful();
            $this->artisan('tenants:seed-expense-categories', ['--tenant' => $own->id])->assertSuccessful();
            $this->artisan('tenants:seed-expense-categories', ['--tenant' => $empty->id])->expectsOutputToContain('Shops given default expense categories: 0');

            $empty->run(fn () => $this->assertSame(9, ExpenseCategory::query()->count()));
            $empty->run(fn () => $this->assertTrue(ExpenseCategory::query()->where('name', 'Rent')->value('is_recurring_eligible')));
            $own->run(fn () => $this->assertSame(['Shoe polish'], ExpenseCategory::query()->pluck('name')->all()));
        } finally {
            $this->deleteTenantHttpFixture($empty->domains()->value('domain'));
            $this->deleteTenantHttpFixture($own->domains()->value('domain'));
        }
    }
}
