<x-mail::message>
# Welcome to {{ $companyName }}

Hi {{ $user->username }},

Your account has been created successfully.

To finish setting up your account, use the verification code sent in the next email.

Thanks,<br>
{{ $companyName }}
</x-mail::message>
