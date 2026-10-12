<x-mail::message>
# Your Poachy payment link

Use this link to top up the Poachy balance for **{{ $shopName }}** by M-Pesa{{ empty($data['amount']) ? '' : ' (suggested amount KSh '.number_format($data['amount']).')' }}. It works for {{ $data['validDays'] }} days and anyone you share it with can pay.

<x-mail::button :url="$payUrl">
Top up now
</x-mail::button>

<x-mail::paybill :account="$accountNumber" :pay-url="$payUrl" />
</x-mail::message>
