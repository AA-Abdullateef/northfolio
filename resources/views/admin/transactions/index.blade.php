@extends('layouts.admin')
@section('title', 'Transactions')
@section('page-title', 'Transactions')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">All Transactions</span>
        <form method="GET" class="flex gap-2" style="flex-wrap:wrap">
            <select name="type" class="form-control" style="width:auto">
                <option value="">All Types</option>
                @foreach(['deposit','withdrawal','transfer'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                @endforeach
            </select>
            <select name="status" class="form-control" style="width:auto">
                <option value="">All Statuses</option>
                @foreach(['pending','completed','cancelled'] as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="asset_id" class="form-control" style="width:auto">
                <option value="">All Assets</option>
                @foreach($assets as $asset)
                    <option value="{{ $asset->id }}" {{ request('asset_id') === $asset->id ? 'selected' : '' }}>
                        {{ $asset->label }}
                    </option>
                @endforeach
            </select>
            <div class="search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search email...">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Type</th><th>Asset</th><th>Amount</th><th>Sub-Method</th><th>Status</th><th>Date</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php
                    $cls = match($tx->status) {
                        'completed' => 'badge-completed',
                        'cancelled' => 'badge-cancelled',
                        default     => 'badge-pending',
                    };
                    $user = $tx->wallet?->user;
                @endphp
                <tr>
                    <td>
                        <div style="font-weight:500">{{ $user?->username ?? 'Unregistered wallet' }}</div>
                        <div class="td-muted">{{ $user?->email ?? $tx->wallet_id }}</div>
                    </td>
                    <td><span class="badge badge-pending">{{ $tx->type }}</span></td>
                    <td class="td-mono">{{ $tx->asset?->name ?? '—' }}</td>
                    <td class="td-mono">{{ \App\Support\Money::display($tx->amount) }}</td>
                    <td class="td-muted">{{ $tx->subMethod?->name ?? '—' }}</td>
                    <td><span class="badge {{ $cls }}">{{ ucfirst($tx->status) }}</span></td>
                    <td class="td-muted">{{ $tx->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.transactions.show',$tx) }}" class="btn btn-ghost btn-sm">
                                View
                            </a>
                            @if($tx->isPending())
                                @if($tx->type !== 'deposit')
                                <form method="POST" action="{{ route('admin.transactions.complete',$tx) }}">
                                    @csrf

                                    <button class="btn btn-success btn-sm" onclick="return confirm('Complete transaction?')">
                                        Complete
                                    </button>
                                </form>
                                @endif

                                <form method="POST" action="{{ route('admin.transactions.cancel',$tx) }}">
                                    @csrf

                                    <button class="btn btn-danger btn-sm" onclick="return confirm('Cancel transaction?')">
                                        Cancel
                                    </button>
                                </form>

                            @endif

                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" style="text-align:center;color:var(--text-faint);padding:32px">No transactions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div style="padding:0 20px">{{ $transactions->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
