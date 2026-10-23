@extends('layouts.admin')

@section('title', 'Transaction Details')
@section('page-title', 'Transaction Details')

@section('topbar-actions')
<a href="{{ route('admin.transactions.index') }}" class="btn btn-ghost btn-sm">
    ← Transactions
</a>
@endsection

@section('content')

@php
    $user = $transaction->wallet?->user;

    $badgeClass = match($transaction->status) {
        'completed' => 'badge-completed',
        'cancelled' => 'badge-cancelled',
        default => 'badge-pending'
    };

    $meta = is_array($transaction->meta)
        ? $transaction->meta
        : json_decode($transaction->meta, true);
@endphp

<div class="grid" style="grid-template-columns:2fr 1fr;gap:24px">

    <div>

        {{-- Summary --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="flex justify-between items-center">

                    <div>
                        <h2>
                            {{ $transaction->typeLabel() }}
                            {{ $transaction->asset?->label }}
                        </h2>

                        <div class="td-muted">
                            Ref: {{ $transaction->reference ?? 'N/A' }}
                        </div>
                    </div>

                    <div style="text-align:right">
                        <div class="amount-display">
                            {{ \App\Support\Money::display($transaction->amount) }}
                        </div>

                        <span class="badge {{ $badgeClass }}">
                            {{ $transaction->statusLabel() }}
                        </span>
                    </div>

                </div>
            </div>
        </div>

        {{-- Transaction Details --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Transaction Details</span>
            </div>

            <div class="card-body">

                <div class="detail-grid">

                    <div class="detail-item">
                        <label>ID</label>
                        <div class="td-mono">{{ $transaction->id }}</div>
                    </div>

                    <div class="detail-item">
                        <label>Reference</label>
                        <div class="td-mono">
                            {{ $transaction->reference ?? '—' }}
                        </div>
                    </div>

                    <div class="detail-item">
                        <label>Type</label>
                        <span class="badge badge-pending">
                            {{ $transaction->typeLabel() }}
                        </span>
                    </div>

                    <div class="detail-item">
                        <label>Status</label>
                        <span class="badge {{ $badgeClass }}">
                            {{ $transaction->statusLabel() }}
                        </span>
                    </div>

                    <div class="detail-item">
                        <label>Amount</label>
                        <div class="td-mono">
                            {{ \App\Support\Money::display($transaction->amount) }}
                        </div>
                    </div>

                    <div class="detail-item">
                        <label>Asset</label>
                        <div>{{ $transaction->asset?->label ?? '—' }}</div>
                    </div>

                    <div class="detail-item">
                        <label>Method</label>
                        <div>{{ $transaction->subMethod?->name ?? '—' }}</div>
                    </div>

                    <div class="detail-item">
                        <label>Created</label>
                        <div class="td-muted">
                            {{ $transaction->created_at->format('d M Y h:i A') }}
                        </div>
                    </div>

                </div>

            </div>
        </div>

        @if($meta)
        <div class="card mt-4">
            <div class="card-header">
                <span class="card-title">Metadata</span>
            </div>

            <div class="card-body">

                @foreach($meta as $key => $value)
                <div class="detail-item">
                    <label>{{ Str::headline($key) }}</label>

                    <div>
                        {{ is_array($value) ? json_encode($value, JSON_PRETTY_PRINT) : $value }}
                    </div>
                </div>
                @endforeach

            </div>
        </div>
        @endif

    </div>

    <div class="flex-column" style="display:flex;gap:24px">

        {{-- User --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">User Information</span>
            </div>

            <div class="card-body">

                <div style="font-weight:600">
                    {{ $user?->username ?? 'Unregistered Wallet' }}
                </div>

                <div class="td-muted">
                    {{ $user?->email ?? $transaction->wallet_id }}
                </div>

                @if($user?->profile)
                <hr class="my-3">

                <div>
                    {{ $user->profile->first_name }}
                    {{ $user->profile->last_name }}
                </div>

                <div class="td-muted">
                    {{ $user->profile->phone ?? '—' }}
                </div>
                @endif

            </div>
        </div>

        {{-- Actions --}}
        <div class="card">
            <div class="card-header">
                <span class="card-title">Actions</span>
            </div>

            <div class="card-body">

                @if($transaction->isPending())

                <div class="flex gap-2">

                    @if($transaction->type !== 'deposit')
                    <form
                        method="POST"
                        action="{{ route('admin.transactions.complete', $transaction) }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="btn btn-success"
                            onclick="return confirm('Complete transaction?')"
                        >
                            Complete
                        </button>
                    </form>
                    @endif

                    <form
                        method="POST"
                        action="{{ route('admin.transactions.cancel', $transaction) }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="btn btn-danger"
                            onclick="return confirm('Cancel transaction?')"
                        >
                            Cancel
                        </button>
                    </form>

                </div>

                @else

                <div class="td-muted">
                    No actions available.
                </div>

                @endif

            </div>
        </div>

    </div>

</div>

@endsection