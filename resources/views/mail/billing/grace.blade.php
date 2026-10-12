<x-mail::message>
# Your Poachy balance has run out

The Poachy balance for **{{ $shopName }}** is **KSh {{ number_format($data['balance'], 2) }}**.

**Everything keeps working today.** If there is no top-up by tomorrow, reports, receiving stock, transfers and settings pause until you pay. Selling never stops.

@if ($payUrl)
<x-mail::button :url="$payUrl">
Top up now
</x-mail::button>
@endif

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
