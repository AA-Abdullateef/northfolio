<x-mail::message>
# New Contact Message

<x-mail::table>
| Field | Details |
|:------|:--------|
| Name | {{ $data['name'] ?? 'N/A' }} |
| Email | {{ $data['email'] ?? 'N/A' }} |
@isset($data['phone'])
| Phone | {{ $data['phone'] }} |
@endisset
@isset($data['subject'])
| Subject | {{ $data['subject'] }} |
@endisset
</x-mail::table>

{{ $data['message'] ?? '' }}
</x-mail::message>
