@extends('layouts.admin')
@section('title', $asset->label . ' — ' . $user->username)
@section('page-title', $asset->label . ' — ' . $user->username)
@section('topbar-actions')
    <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ghost btn-sm">← User</a>
@endsection

@section('content')
<div class="stat-grid mb-6">
    <div class="stat-card">
        <div class="stat-label">{{ $asset->label }} Balance</div>
        <div class="stat-value {{ $balance > 0 ? 'green' : '' }}">{{ \App\Support\Money::display($balance) }}</div>
        <div class="stat-sub">Derived from ledger</div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Transaction History</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Type</th><th>Amount</th><th>Sub-Method</th><th>Reference</th><th>Status</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $tx)
                @php
                    $cls = match($tx->status) {
                        'completed' => 'badge-completed',
                        'cancelled' => 'badge-cancelled',
                        default     => 'badge-pending',
                    };
                @endphp
                <tr>
                    <td><span class="badge badge-pending">{{ $tx->type }}</span></td>
                    <td class="td-mono">{{ \App\Support\Money::display($tx->amount) }}</td>
                    <td class="td-muted">{{ $tx->subMethod?->name ?? '—' }}</td>
                    <td class="td-muted" style="max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $tx->reference ?? '—' }}
                    </td>
                    <td><span class="badge {{ $cls }}">{{ ucfirst($tx->status) }}</span></td>
                    <td class="td-muted">{{ $tx->created_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;color:var(--text-faint);padding:32px">No transactions.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())
        <div style="padding:0 16px">{{ $transactions->links() }}</div>
    @endif
</div>
@endsection