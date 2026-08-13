@extends('emails.layouts.app')

@section('title', 'Business Submission Needs Updates')

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    Hi {{ $ownerName }},
</h2>

<p style="margin-bottom: 1rem; color: #4b5563;">
    We reviewed the submission for <strong>{{ $businessName }}</strong>, but it needs updates before it can be approved.
</p>

@if($notes)
<div style="background-color: #f9fafb; border-left: 4px solid #dc2626; padding: 1rem; margin: 1.5rem 0; border-radius: 0.375rem;">
    <p style="margin-top: 0; margin-bottom: 0.5rem; color: #111827; font-weight: 600;">Admin notes</p>
    <p style="margin: 0; color: #4b5563; white-space: pre-line;">{{ $notes }}</p>
</div>
@endif

<p style="margin-bottom: 0; color: #4b5563;">
    Please update your business details and submit them again for review.
</p>
@endsection
