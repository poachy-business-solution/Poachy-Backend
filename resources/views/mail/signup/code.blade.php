<x-mail::message>
# Confirm your email

Hi {{ $ownerName }}, enter this code on the Poachy sign-up page:

<x-mail::panel>
<span style="font-size: 28px; font-weight: 800; letter-spacing: 6px;">{{ $code }}</span>
</x-mail::panel>

It works for {{ $minutes }} minutes. If you did not try to open a Poachy shop, ignore this email.
</x-mail::message>
