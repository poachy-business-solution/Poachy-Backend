<x-mail::message>
# Poachy is in limited mode

**{{ $shopName }}** is in limited mode because the Poachy balance is **KSh {{ number_format($data['balance'], 2) }}**.

You can still sell and sync. Reports, receiving stock, transfers and settings come back the moment your top-up arrives.

@if ($payUrl)
<x-mail::button :url="$payUrl">
Top up now
</x-mail::button>
@endif

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
