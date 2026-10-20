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
        // Derived from APP_URL (or the MPESA_C2B_*_URL base when set), ending in the callback
        // secret (App\Support\MpesaCallbacks); shown masked.
        $validationUrl = MpesaCallbacks::url('c2b_validation');
        $confirmationUrl = MpesaCallbacks::url('c2b_confirmation');
        $responseType = $this->option('response-type');

        $this->info("Registering C2B URLs ({$env})...");
        $this->line('  Validation URL:   '.MpesaCallbacks::masked('c2b_validation'));
        $this->line('  Confirmation URL: '.MpesaCallbacks::masked('c2b_confirmation'));
        $this->line("  Response Type:    {$responseType}");

        if (! str_starts_with($validationUrl, 'https://')) {
            $this->error('Safaricom needs HTTPS callback URLs: set APP_URL to the public https:// address (or MPESA_C2B_*_URL).');

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
