<?php

namespace App\Events\Central\Billing;

use App\Models\BillingAccount;
use App\Models\BillingLedgerEntry;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Money was added to a shop's Poachy balance (top-up, credit, refund).
 */
class BillingBalanceCredited
{
    use Dispatchable;

    public function __construct(public BillingAccount $account, public BillingLedgerEntry $entry) {}
}
