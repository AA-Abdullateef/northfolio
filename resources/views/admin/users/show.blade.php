@extends('layouts.admin')
@section('title', $user->username)
@section('page-title', $user->username)

@section('topbar-actions')
    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a>
    <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-sm">← Users</a>
@endsection

@section('content')
<div class="grid-3 mb-6">

    {{-- Profile Card --}}
    <div class="card" style="grid-column:span 2">
        <div class="card-header">
            <span class="card-title">Account Details</span>
            <div class="flex gap-2">
                @if($user->two_factor)
                    <span class="badge badge-active">2FA On</span>
                @endif
                @if($user->email_verified_at)
                    <span class="badge badge-active">Verified</span>
                @else
                    <span class="badge badge-pending">Unverified</span>
                @endif
                @if($user->status)
                    <span class="badge {{ $user->status === 'active' ? 'badge-active' : 'badge-cancelled' }}">
                        {{ ucfirst($user->status) }}
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">Username</div>
                    <div class="detail-item-value mono">{{ $user->username }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Email</div>
                    <div class="detail-item-value">{{ $user->email }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Full Name</div>
                    <div class="detail-item-value">{{ $user->fullName() }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Phone</div>
                    <div class="detail-item-value">{{ $user->profile?->phone ?? '—' }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Joined</div>
                    <div class="detail-item-value">{{ $user->created_at->format('d M Y, H:i') }}</div>
                </div>
                <div class="detail-item" style="grid-column:span 2">
                    <div class="detail-item-label">User ID</div>
                    <div class="detail-item-value mono" style="font-size:11px;color:var(--text-muted)">{{ $user->id }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Actions</span></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost">Edit Profile</a>
            <form method="POST" action="{{ route('admin.users.toggle-2fa', $user) }}">
                @csrf
                <button type="submit" class="btn btn-ghost" style="width:100%;justify-content:center">
                    {{ $user->two_factor ? 'Disable 2FA' : 'Enable 2FA' }}
                </button>
            </form>
            @if(!$user->email_verified_at)
                <form method="POST" action="{{ route('admin.users.verify-email', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center"
                            onclick="return confirm('Verify this user email address?')"> Verify Email
                    </button>
                </form>
            @endif
            <div class="divider"></div>
            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                  onsubmit="return confirm('Permanently delete {{ $user->username }}?')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger" style="width:100%;justify-content:center">Delete User</button>
            </form>
        </div>
    </div>
</div>

{{-- Asset Balances --}}
<div class="card mb-6">
    <div class="card-header"><span class="card-title">Asset Balances</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Asset</th><th>Label</th><th>Balance</th><th></th></tr>
            </thead>
            <tbody>
                @foreach($assets as $asset)
                <tr>
                    <td class="td-mono">{{ strtoupper($asset->name) }}</td>
                    <td class="td-muted">{{ $asset->label }}</td>
                    <td class="td-mono" style="color:{{ ($balances[$asset->id] ?? 0) > 0 ? 'var(--green)' : 'var(--text-muted)' }}">
                        {{ \App\Support\Money::display($balances[$asset->id] ?? 0) }}
                    </td>
                    <td>
                        <a href="{{ route('admin.users.asset-details', [$user, $asset]) }}" class="btn btn-ghost btn-sm">History</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Reset Password --}}
<div class="card">
    <div class="card-header"><span class="card-title">Reset Password</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">
            @csrf
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Repeat password">
                </div>
            </div>
            @error('password')<div class="form-error">{{ $message }}</div>@enderror
            <button type="submit" class="btn btn-primary">Reset Password</button>
        </form>
    </div>
</div>
@endsection