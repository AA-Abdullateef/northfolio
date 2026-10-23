<x-mail::message>
# Transaction Update

Hi {{ $user->username }},

Your **{{ $transaction->typeLabel() }}** has been updated.

<x-mail::table>
| Field | Details |
|:------|:--------|
| Type | {{ $transaction->typeLabel() }} |
| Asset | {{ $transaction->asset?->label ?? 'N/A' }} ({{ strtoupper($transaction->asset?->name ?? '') }}) |
| Amount | {{ \App\Support\Money::display($transaction->amount) }} |
| Status | {{ $transaction->statusLabel() }} |
| Transaction ID | `{{ $transaction->id }}` |
| Date | {{ $transaction->updated_at->format('d M Y, H:i') }} UTC |
</x-mail::table>

@if($transaction->reference)
**Reference:** {{ $transaction->reference }}
@endif

If you have questions, please contact our support team.

Thanks,
**{{ $companyName }}**
</x-mail::message>