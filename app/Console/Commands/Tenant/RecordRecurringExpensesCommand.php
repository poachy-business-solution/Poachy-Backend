<?php

namespace App\Console\Commands\Tenant;

use App\Models\Tenant;
use App\Services\Tenant\Expenses\RecurringExpenseService;
use App\Services\Tenant\Shift\TillService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/** Records every repeating cost that is due (rent, salaries…) in every shop. Safe to run again. */
class RecordRecurringExpensesCommand extends Command
{
    protected $signature = 'expenses:record-recurring {--tenant= : Only this tenant id} {--as-of= : Pretend today is this date (Y-m-d)}';

    protected $description = 'Record repeating expenses that are due';

    public function handle(): int
    {
        $total = 0;
        Tenant::query()->when($this->option('tenant'), fn ($q, $id) => $q->whereKey($id))->orderBy('created_at')
            ->each(function (Tenant $tenant) use (&$total) {
                try {
                    $tenant->run(function () use (&$total) {
                        $today = CarbonImmutable::parse($this->option('as-of') ?: app(TillService::class)->localToday());
                        $total += app(RecurringExpenseService::class)->recordDue($today);
                    });
                } catch (Throwable $e) {
                    report($e);
                    $this->warn("{$tenant->id}: {$e->getMessage()}");
                }
            });

        $this->info("Repeating expenses recorded: {$total}");

        return self::SUCCESS;
    }
}
