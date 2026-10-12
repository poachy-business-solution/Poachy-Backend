@props(['account', 'paybill' => null, 'payUrl' => null, 'amount' => null])
@php($paybill ??= config('messaging.paybill_number'))
Pay with M-Pesa → Lipa na M-Pesa → Pay Bill
Business number: {{ $paybill }}
Account number: {{ $account }}
@if ($amount)
Amount: KSh {{ number_format($amount) }} (any amount from KSh 10 works)
@endif
@if ($payUrl)
Or pay online: {{ $payUrl }}
@endif
