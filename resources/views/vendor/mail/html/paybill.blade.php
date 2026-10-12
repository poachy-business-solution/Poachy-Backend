@props(['account', 'paybill' => null, 'payUrl' => null, 'amount' => null])
@php($paybill ??= config('messaging.paybill_number'))
<table class="panel paybill" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="panel-content">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr><td>Pay with M-Pesa → Lipa na M-Pesa → Pay Bill</td></tr>
<tr><td>Business number</td></tr>
<tr><td class="value">{{ $paybill }}</td></tr>
<tr><td>Account number</td></tr>
<tr><td class="value">{{ $account }}</td></tr>
@if ($amount)
<tr><td>Amount: KSh {{ number_format($amount) }} (any amount from KSh 10 works)</td></tr>
@endif
@if ($payUrl)
<tr><td>Or pay online: <a href="{{ $payUrl }}">{{ $payUrl }}</a></td></tr>
@endif
</table>
</td>
</tr>
</table>
