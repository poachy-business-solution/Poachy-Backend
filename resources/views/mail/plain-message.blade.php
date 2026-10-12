<x-mail::message>
# {{ $heading }}

@foreach ($paragraphs as $paragraph)
{{ $paragraph }}

@endforeach
@if ($buttonLabel && $buttonUrl)
<x-mail::button :url="$buttonUrl">
{{ $buttonLabel }}
</x-mail::button>
@endif
@if ($paybillAccount)
<x-mail::paybill :account="$paybillAccount" :pay-url="$payUrl" />
@endif
</x-mail::message>
