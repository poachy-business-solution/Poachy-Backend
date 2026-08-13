<?php

namespace App\Console\Commands\Tenant;

use App\Jobs\Tenant\SendShiftRemindersJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class SendShiftRemindersCommand extends Command
{
    protected $signature = 'shifts:send-reminders {--hours= : Override configured reminder lead time}';

    protected $description = 'Send upcoming shift reminders for all tenants';

    public function handle(): int
    {
        if (! config('shift.send_shift_reminders', true)) {
            $this->info('Shift reminders are disabled.');

            return Command::SUCCESS;
        }

        $hoursBefore = $this->option('hours') !== null
            ? (int) $this->option('hours')
            : null;

        $dispatched = 0;

        foreach (Tenant::all() as $tenant) {
            SendShiftRemindersJob::dispatch($tenant->id, $hoursBefore);
            $dispatched++;
        }

        $this->info("Shift reminder jobs dispatched for {$dispatched} tenants");

        return Command::SUCCESS;
    }
}
