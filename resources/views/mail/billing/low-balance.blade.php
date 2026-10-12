<x-mail::message>
# Top up to keep Poachy running

The Poachy balance for **{{ $shopName }}** is **KSh {{ number_format($data['balance'], 2) }}** — enough for about {{ $data['daysCovered'] }} more trading {{ (int) $data['daysCovered'] === 1 ? 'day' : 'days' }}.

Top up any amount from KSh 10 by M-Pesa so nothing pauses.

@if ($payUrl)
<x-mail::button :url="$payUrl">
Top up now
</x-mail::button>
@endif

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
