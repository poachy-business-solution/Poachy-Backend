@extends('emails.layouts.app')

@section('title', 'Sync Failed')

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    Sync Failed: {{ $syncType }}
</h2>

<p style="margin-bottom: 1rem; color: #4b5563;">
    A Poachy sync job failed permanently after exhausting its retries.
</p>

<div style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin: 1.5rem 0;">
    <p style="margin: 0 0 0.5rem 0; color: #111827;"><strong>Sync queue ID:</strong> {{ $syncQueueId }}</p>
    <p style="margin: 0; color: #111827;"><strong>Error:</strong> {{ $errorMessage }}</p>
</div>

@if(! empty($details))
<div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin-top: 0; margin-bottom: 0.75rem; color: #1e40af; font-weight: 600;">Details</p>
    @foreach($details as $label => $value)
        <p style="margin: 0 0 0.4rem 0; color: #1e3a8a;">
            <strong>{{ ucfirst(str_replace('_', ' ', (string) $label)) }}:</strong>
            {{ is_scalar($value) || $value === null ? $value : json_encode($value) }}
        </p>
    @endforeach
</div>
@endif

<p style="margin-bottom: 0; color: #4b5563;">
    Please review the sync queue and retry or correct the underlying data issue.
</p>
@endsection
