<x-mail::message>
# Welcome to Poachy, {{ $data['ownerName'] }}

**{{ $shopName }}** is ready. Your workspace name is:

<x-mail::panel>
**{{ $data['workspace'] }}**
</x-mail::panel>

**Get started**

1. Install the Poachy app on the phone or tablet you sell from.
2. Enter the workspace name above, then sign in with this email and the password you chose.
3. Add your products (or scan their barcodes) and make your first sale.

**Your free trial runs until {{ $data['trialEndsOn'] }}.** Nothing is charged until then, and no card is needed.

After the trial, Poachy costs KSh {{ number_format($data['dayPrice']) }} only on days a branch sells — never more than KSh {{ number_format($data['monthCap']) }} a month for your first branch. Days you don't sell are free.

Keep a balance by paying any amount by M-Pesa. Your Poachy account number is **{{ $accountNumber }}**:

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />

Questions? Just reply to this email.
</x-mail::message>
