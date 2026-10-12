<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('messaging.site_url')">
Poachy
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
Poachy · {{ config('messaging.support_email') }} · {{ config('messaging.support_phone') }}<br>
© {{ date('Y') }} Poachy. Nairobi, Kenya.
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
