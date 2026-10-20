<?php

namespace App\Services\Central\Payouts;

use App\Models\MarketplaceFeeSetting;
use Illuminate\Support\Carbon;

/**
 * The payout fees in force on a date (item 49): the latest setting Poachy finance scheduled on or
 * before it, falling back to config/payouts.php when none exists yet.
 */
class MarketplaceFeeService
{
    /**
     * @return array{mpesa_fee_percent: float, card_fee_percent: float, transfer_fee: float, hold_hours: int, min_payout: int, effective_from: string|null}
     */
    public function on(?Carbon $date = null): array
    {
        $row = MarketplaceFeeSetting::query()
            ->where('effective_from', '<=', ($date ?? now())->copy()->timezone('Africa/Nairobi')->toDateString())
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();

        return [
            'mpesa_fee_percent' => (float) ($row?->mpesa_fee_percent ?? config('payouts.fees.mpesa_percent', 0)),
            'card_fee_percent' => (float) ($row?->card_fee_percent ?? config('payouts.fees.card_percent', 0)),
            'transfer_fee' => (float) ($row?->transfer_fee ?? config('payouts.fees.transfer_fee', 0)),
            'hold_hours' => (int) ($row?->hold_hours ?? config('payouts.hold_hours', 24)),
            'min_payout' => (int) ($row?->min_payout ?? config('payouts.min_amount', 10)),
            'effective_from' => $row?->effective_from?->toDateString(),
        ];
    }
}
