@extends('layouts.admin')
@section('title', 'User Tokens')
@section('page-title', 'User Tokens')

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Active 2FA / Sensitive Action Tokens</span>
        <form method="GET" class="flex gap-2">
            <select name="purpose" class="form-control" style="width:auto">
                <option value="">All Purposes</option>
                <option value="two_factor"  {{ request('purpose') === 'two_factor'  ? 'selected' : '' }}>Two-Factor</option>
                <option value="deactivation"{{ request('purpose') === 'deactivation'? 'selected' : '' }}>Deactivation</option>
            </select>
            <button type="submit" class="btn btn-ghost btn-sm">Filter</button>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Purpose</th>
                    <th>Attempts</th>
                    <th>Expires At</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tokens as $token)
                <tr>
                    <td>
                        <div style="font-weight:500">{{ $token->user->username }}</div>
                        <div class="td-muted">{{ $token->user->email }}</div>
                    </td>
                    <td>
                        <span class="badge {{ $token->purpose === 'two_factor' ? 'badge-processing' : 'badge-pending' }}">
                            {{ str_replace('_', ' ', $token->purpose) }}
                        </span>
                    </td>
                    <td class="td-mono">{{ $token->attempts }}</td>
                    <td class="td-muted">
                        @if($token->expires_at)
                            @if($token->isExpired())
                                <span style="color:var(--red)">Expired {{ $token->expires_at->diffForHumans() }}</span>
                            @else
                                {{ $token->expires_at->diffForHumans() }}
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td class="td-muted">{{ $token->created_at->format('d M Y, H:i') }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.tokens.destroy', $token) }}">
                            @csrf @method('DELETE')
                            <button class="btn btn-danger btn-sm" onclick="return confirm('Revoke this token?')">
                                Revoke
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--text-faint);padding:32px">
                        No active tokens found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($tokens->hasPages())
        <div style="padding:0 20px">{{ $tokens->withQueryString()->links() }}</div>
    @endif
</div>
@endsection