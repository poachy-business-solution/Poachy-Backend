<?php

namespace App\Mail\Central\Billing;

use App\Mail\PoachyMail;

/**
 * Receipt for a Poachy balance top-up.
 */
class BillingReceiptMail extends PoachyMail
{
    public function __construct(
        public string $shopName,
        public float $amount,
        public string $receipt,
        public float $newBalance,
        public string $accountNumber,
        public string $paidAt,
        public string $method,
    ) {
        $this->onQueue('default');
    }

    protected function subjectLine(): string
    {
        return 'Payment received: KSh '.number_format($this->amount).' added to your Poachy balance';
    }

    protected function markdownView(): string
    {
        return 'mail.billing.receipt';
    }
}
