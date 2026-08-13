@extends('emails.layouts.app')

@section('title', $subjectLine)

@section('content')
<h2 style="color: #111827; font-size: 1.5rem; font-weight: 700; margin-top: 0; margin-bottom: 1rem;">
    {{ $subjectLine }}
</h2>

<div style="color: #4b5563; font-size: 1rem; line-height: 1.6; white-space: pre-line;">
    {{ $body }}
</div>
@endsection
