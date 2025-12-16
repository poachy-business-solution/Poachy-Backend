<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\TaxRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TaxRateObserver
{
    public function created(TaxRate $taxRate): void
    {
        $this->clearCache();
    }

    public function updated(TaxRate $taxRate): void
    {
        $this->clearCache();
    }

    protected function clearCache(): void
    {
        try {
            if (tenant()) {
                Cache::tags(['tenant', tenant()->id, 'tax_rates'])->flush();
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear tax rate cache', [
                'tenant_id' => tenant()?->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
