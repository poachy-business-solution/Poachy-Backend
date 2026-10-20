<?php

namespace App\Console\Commands;

use App\Exceptions\MpesaException;
use App\Services\Shared\Mpesa\MpesaService;
use App\Support\MpesaCallbacks;
use Illuminate\Console\Command;

class MpesaRegisterC2BCommand extends Command
{
    protected $signature = 'mpesa:register-c2b
                                {--response-type=Cancelled : ResponseType sent to Daraja (Cancelled or Completed)}';

    protected $description = 'Register C2B (Paybill) ValidationURL and ConfirmationURL with Safaricom Daraja. Run once per shortcode per environment.';

    public function handle(MpesaService $mpesa): int
    {
        $env = config('mpesa.environment', 'sandbox');
        // The registered URLs end in the callback secret (App\Support\MpesaCallbacks); shown masked.
        $validationUrl = config('mpesa.c2b_validation_url') ? MpesaCallbacks::url('c2b_validation') : '';
        $confirmationUrl = config('mpesa.c2b_confirmation_url') ? MpesaCallbacks::url('c2b_confirmation') : '';
        $responseType = $this->option('response-type');

        $this->info("Registering C2B URLs ({$env})...");
        $this->line('  Validation URL:   '.($validationUrl ? MpesaCallbacks::masked('c2b_validation') : ''));
        $this->line('  Confirmation URL: '.($confirmationUrl ? MpesaCallbacks::masked('c2b_confirmation') : ''));
        $this->line("  Response Type:    {$responseType}");

        if (! $validationUrl || ! $confirmationUrl) {
            $this->error('MPESA_C2B_VALIDATION_URL and MPESA_C2B_CONFIRMATION_URL must be set in your .env file.');

            return self::FAILURE;
        }

        try {
            $result = $mpesa->registerC2BUrls($validationUrl, $confirmationUrl, $responseType);

            $this->info('C2B URLs registered successfully.');
            $this->table(['Field', 'Value'], array_map(
                fn ($k, $v) => [$k, $v],
                array_keys($result),
                array_values($result),
            ));

            return self::SUCCESS;
        } catch (MpesaException $e) {
            $this->error("Registration failed: {$e->getMessage()}");
            $this->line("  Daraja error code: {$e->darajaErrorCode}");

            return self::FAILURE;
        }
    }
}
