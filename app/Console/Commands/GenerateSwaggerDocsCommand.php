<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateSwaggerDocsCommand extends Command
{
    protected $signature = 'docs:generate
                                {--name=default : Documentation name to generate}';

    protected $description = 'Generate Swagger/OpenAPI documentation. Wraps l5-swagger:generate with graceful warning handling.';

    public function handle(): int
    {
        // swagger-php fires trigger_error(E_USER_WARNING) for non-fatal annotation
        // issues (e.g. duplicate merge attempts). Laravel's error handler normally
        // converts these to ErrorException. We suppress E_USER_WARNING here so
        // generation completes and only truly fatal errors surface as exceptions.
        set_error_handler(function (int $errno, string $errstr) {
            if ($errno === E_USER_WARNING || $errno === E_USER_NOTICE) {
                Log::debug("Swagger warning suppressed: {$errstr}");

                return true; // handled — do not propagate
            }

            return false; // let Laravel handle anything else
        });

        try {
            $this->call('l5-swagger:generate', ['documentation' => $this->option('name')]);

            $this->info('Swagger docs generated successfully.');

            return self::SUCCESS;
        } finally {
            restore_error_handler();
        }
    }
}
