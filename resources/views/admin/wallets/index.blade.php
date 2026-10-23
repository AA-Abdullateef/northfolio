@extends('layouts.admin')
@section('title', 'Wallets')
@section('page-title', 'Wallet Accounts')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">All Wallet Accounts</span>
        <form method="GET" class="flex gap-2">
            <div class="search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search username, email, or wallet...">
            </div>
            <button type="submit" class="btn btn-ghost btn-sm">Search</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>User</th><th>Fingerprint</th><th>Public ID</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($wallets as $wallet)
                <tr>
                    <td>
                        <div style="font-weight:500">{{ $wallet->user?->username ?? 'Unregistered wallet' }}</div>
                        <div class="td-muted">{{ $wallet->user?->email ?? 'Profile not completed' }}</div>
                    </td>
                    <td class="td-mono" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--text-faint)">
                        {{ $wallet->fingerprint }}
                    </td>
                    <td class="td-mono" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;color:var(--text-faint)">
                        {{ $wallet->public_id }}
                    </td>
                    <td class="td-muted">{{ $wallet->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.wallets.show', $wallet) }}" class="btn btn-ghost btn-sm">View</a>
                            <form method="POST" action="{{ route('admin.wallets.destroy', $wallet) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this wallet? The user account will remain.')">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-faint);padding:32px">No wallet accounts found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($wallets->hasPages())
        <div style="padding:0 20px">{{ $wallets->withQueryString()->links() }}</div>
    @endif
</div>
@endsection
