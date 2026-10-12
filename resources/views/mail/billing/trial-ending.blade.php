<x-mail::message>
# {{ (int) $data['daysLeft'] === 1 ? 'Your free trial ends tomorrow' : 'Your free trial ends in '.$data['daysLeft'].' days' }}

The Poachy free trial for **{{ $shopName }}** ends on **{{ $data['trialEndsOn'] }}**.

After that, Poachy costs KSh {{ number_format($data['dayPrice']) }} only on days a branch sells — never more than KSh {{ number_format($data['monthCap']) }} a month for your first branch. Days you don't sell are free.

Add a balance now so nothing pauses when the trial ends. Any amount from KSh 10 works.

@if ($payUrl)
<x-mail::button :url="$payUrl">
Top up now
</x-mail::button>
@endif

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
