<x-mail::message>
# Deposit Initiated

Hi {{ $user->username }},

Your deposit request has been received and is **awaiting payment confirmation**.

<x-mail::table>
| Field | Details |
|:------|:--------|
| Asset | {{ $asset?->label ?? 'N/A' }} ({{ strtoupper($asset?->name ?? '') }}) |
| Amount | {{ \App\Support\Money::display($transaction->amount) }} |
| Status | {{ ucfirst($transaction->status) }} |
| Reference | `{{ $transaction->id }}` |
</x-mail::table>

Complete the payment on the deposit page. Your balance is credited automatically once Stripe confirms it.

If you did not initiate this deposit, please contact support immediately.

Thanks,
**{{ $companyName }}**
</x-mail::message>