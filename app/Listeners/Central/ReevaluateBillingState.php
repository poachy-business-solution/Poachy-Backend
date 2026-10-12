<?php

namespace App\Listeners\Central;

use App\Events\Central\Billing\BillingBalanceCredited;
use App\Services\Central\Billing\BillingStateService;

/**
 * A top-up can lift a shop out of grace or limited mode at once.
 */
class ReevaluateBillingState
{
    public function __construct(private BillingStateService $states) {}

    public function handle(BillingBalanceCredited $event): void
    {
        $this->states->evaluate($event->account->fresh());
    }
}
