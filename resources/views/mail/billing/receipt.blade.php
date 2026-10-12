<x-mail::message>
# Thank you — payment received

We added **KSh {{ number_format($amount) }}** to the Poachy balance for **{{ $shopName }}**.

<x-mail::table>
| | |
|:--|--:|
| Amount | KSh {{ number_format($amount, 2) }} |
| M-Pesa receipt | {{ $receipt }} |
| Paid | {{ $paidAt }} |
| Method | {{ $method }} |
| New balance | KSh {{ number_format($newBalance, 2) }} |
</x-mail::table>

Poachy charges only on the days a branch sells, and never more than the monthly cap.

To top up again, pay any amount from KSh 10:

<x-mail::paybill :account="$accountNumber" />
</x-mail::message>
