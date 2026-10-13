<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\ExpenseCategory;
use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Console\Command;
use Throwable;

/** Gives shops that have no expense categories the everyday defaults (one-off backfill). */
class SeedExpenseCategoriesCommand extends Command
{
    protected $signature = 'tenants:seed-expense-categories {--tenant= : Only this tenant id}';

    protected $description = 'Add the default expense categories to shops that have none';

    public function handle(): int
    {
        $seeded = 0;
        Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->orderBy('created_at')
            ->each(function (Tenant $tenant) use (&$seeded) {
                try {
                    $tenant->run(function () use (&$seeded) {
                        if (ExpenseCategory::query()->exists()) {
                            return;
                        }
                        (new ExpenseCategorySeeder)->run();
                        $seeded++;
                    });
                } catch (Throwable $e) {
                    $this->warn("{$tenant->id}: {$e->getMessage()}");
                }
            });

        $this->info("Shops given default expense categories: {$seeded}");

        return self::SUCCESS;
    }
}
