<x-mail::message>
# Your temporary password

Hello {{ $ownerName }}, use these details to sign in to the Poachy app for the first time:

<x-mail::panel>
Workspace: **{{ $workspace }}**<br>
Email: **{{ $email }}**<br>
Temporary password: **{{ $password }}**
</x-mail::panel>

The app asks you to choose your own password straight away. Don't share this email.
</x-mail::message>
