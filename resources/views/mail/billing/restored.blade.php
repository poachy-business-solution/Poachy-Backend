<x-mail::message>
# Poachy is fully back on

Thanks — the top-up for **{{ $shopName }}** arrived and everything is unlocked again. Your balance is now **KSh {{ number_format($data['balance'], 2) }}**.

To top up again any time, pay any amount from KSh 10:

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
