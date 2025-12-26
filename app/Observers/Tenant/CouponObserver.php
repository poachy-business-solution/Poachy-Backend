<?php

namespace App\Observers\Tenant;

use App\Models\Tenant\Coupon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CouponObserver
{
    /**
     * Handle the Coupon "created" event.
     */
    public function created(Coupon $coupon): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Coupon "updated" event.
     */
    public function updated(Coupon $coupon): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Coupon "deleted" event.
     */
    public function deleted(Coupon $coupon): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Coupon "restored" event.
     */
    public function restored(Coupon $coupon): void
    {
        $this->invalidateCache();
    }

    /**
     * Handle the Coupon "force deleted" event.
     */
    public function forceDeleted(Coupon $coupon): void
    {
        $this->invalidateCache();
    }

    /**
     * Invalidate tenant-specific coupon cache.
     */
    protected function invalidateCache(): void
    {
        Cache::tags(['tenant', tenant()->id, 'coupons'])->flush();
    }
}
