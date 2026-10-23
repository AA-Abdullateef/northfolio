@extends('layouts.admin')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="stat-grid mb-6">
    <div class="stat-card">
        <div class="stat-label">Total Deposits</div>
        <div class="stat-value amber">${{ number_format($totals['total_deposits'] ?? 0, 2) }}</div>
        <div class="stat-sub">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Withdrawals</div>
        <div class="stat-value red">${{ number_format($totals['total_withdrawals'] ?? 0, 2) }}</div>
        <div class="stat-sub">Pending + completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Transfers</div>
        <div class="stat-value">${{ number_format($totals['total_transfers'] ?? 0, 2) }}</div>
        <div class="stat-sub">Completed</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Users</div>
        <div class="stat-value green">{{ \App\Support\Money::display($totalUsers) }}</div>
        <div class="stat-sub">Customers</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Deposits</div>
        <div class="stat-value amber">{{ $pendingDeposits }}</div>
        <div class="stat-sub">Awaiting review</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value red">{{ $pendingWithdrawals }}</div>
        <div class="stat-sub">Awaiting processing</div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-header">
        <span class="card-title">Platform Asset Totals</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Asset</th><th>Deposits</th><th>Withdrawals</th><th>Transfers</th></tr>
            </thead>
            <tbody>
                @forelse($totals as $total)
                <tr>
                    <td class="td-mono">{{ strtoupper($total['asset']->name) }}</td>
                    <td class="td-mono">{{ number_format($total['deposits']) }}</td>
                    <td class="td-mono">{{ \App\Support\Money::display($total['withdrawals']) }}</td>
                    <td class="td-mono">{{ \App\Support\Money::display($total['transfers']) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:var(--text-faint);padding:32px">No asset activity yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Completed Transactions</span>
        <a href="{{ route('admin.transactions.index') }}" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Type</th><th>Asset</th><th>Amount</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($recentTransactions as $tx)
                @php($user = $tx->wallet?->user)
                <tr>
                    <td>
                        <div style="font-weight:500">{{ $user?->username ?? 'Unregistered wallet' }}</div>
                        <div class="td-muted">{{ $user?->email ?? $tx->wallet_id }}</div>
                    </td>
                    <td><span class="badge badge-active">{{ $tx->type }}</span></td>
                    <td class="td-mono">{{ $tx->asset?->name ?? '—' }}</td>
                    <td class="td-mono">{{ \App\Support\Money::display($tx->amount) }}</td>
                    <td class="td-muted">{{ $tx->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-faint);padding:32px">No completed transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
