<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Promotion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PromotionObserver
{
    /**
     * Handle the Promotion "creating" event
     */
    public function creating(Promotion $promotion): void
    {
        // Auto-generate code if not provided
        if (empty($promotion->code)) {
            $promotion->code = $this->generateUniqueCode();
        }

        // Ensure code is uppercase
        $promotion->code = strtoupper($promotion->code);
    }

    /**
     * Handle the Promotion "created" event
     */
    public function created(Promotion $promotion): void
    {
        $this->clearCache();

        Log::info('Promotion created via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'code' => $promotion->code,
        ]);
    }

    /**
     * Handle the Promotion "updating" event
     */
    public function updating(Promotion $promotion): void
    {
        // Ensure code remains uppercase
        if ($promotion->isDirty('code')) {
            $promotion->code = strtoupper($promotion->code);
        }
    }

    /**
     * Handle the Promotion "updated" event
     */
    public function updated(Promotion $promotion): void
    {
        $this->clearCache();

        Log::info('Promotion updated via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'changes' => $promotion->getChanges(),
        ]);
    }

    /**
     * Handle the Promotion "deleted" event
     */
    public function deleted(Promotion $promotion): void
    {
        $this->clearCache();

        Log::info('Promotion deleted via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
            'code' => $promotion->code,
        ]);
    }

    /**
     * Handle the Promotion "restored" event
     */
    public function restored(Promotion $promotion): void
    {
        $this->clearCache();

        Log::info('Promotion restored via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Handle the Promotion "force deleted" event
     */
    public function forceDeleted(Promotion $promotion): void
    {
        $this->clearCache();

        Log::warning('Promotion force deleted via observer', [
            'tenant_id' => tenant()->id,
            'promotion_id' => $promotion->id,
        ]);
    }

    /**
     * Generate a unique promotion code
     */
    protected function generateUniqueCode(): string
    {
        do {
            $code = 'PROMO-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        } while (Promotion::where('code', $code)->exists());

        return $code;
    }

    /**
     * Clear all promotion-related cache
     */
    protected function clearCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'promotions'])->flush();
    }
}
