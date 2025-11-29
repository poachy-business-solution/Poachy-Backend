<?php

namespace App\Console\Commands;

use App\Models\Otp;
use Illuminate\Console\Command;

class CleanupExpiredOtps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up expired and used OTP codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = Otp::where(function ($query) {
            $query->where('is_used', true)
                ->orWhere('expires_at', '<', now());
        })->delete();

        $this->info("Deleted {$deleted} expired/used OTP codes.");

        return self::SUCCESS;
    }
}
