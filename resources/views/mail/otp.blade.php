<x-mail::message>
# {{ $heading }}

Hi {{ $user->username }},

{{ $intro }}

<x-mail::panel>
{{ $otp }}
</x-mail::panel>

This code expires in {{ $expiryMinutes }} minutes.

If you did not request this code, you can ignore this email.

Thanks,<br>
{{ $companyName }}
</x-mail::message>
