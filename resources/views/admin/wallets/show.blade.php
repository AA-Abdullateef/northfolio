@extends('layouts.admin')
@section('title', 'Wallet - ' . ($wallet->user?->username ?? 'Unregistered'))
@section('page-title', 'Wallet - ' . ($wallet->user?->username ?? 'Unregistered'))
@section('topbar-actions')
    <a href="{{ route('admin.wallets.index') }}" class="btn btn-ghost btn-sm">Back to Wallets</a>
    @if($wallet->user)
        <a href="{{ route('admin.users.show', $wallet->user) }}" class="btn btn-ghost btn-sm">View User</a>
    @endif
@endsection

@section('content')
<div class="grid-2">
    <div class="card">
        <div class="card-header"><span class="card-title">Wallet Identity</span></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">Wallet ID</div>
                    <div class="detail-item-value mono" style="font-size:11px;color:var(--text-muted)">{{ $wallet->id }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Created</div>
                    <div class="detail-item-value">{{ $wallet->created_at->format('d M Y, H:i') }}</div>
                </div>
                <div class="detail-item" style="grid-column:span 2">
                    <div class="detail-item-label">Fingerprint</div>
                    <div class="detail-item-value mono" style="font-size:11px;word-break:break-all;color:var(--text-muted)">{{ $wallet->fingerprint }}</div>
                </div>
                <div class="detail-item" style="grid-column:span 2">
                    <div class="detail-item-label">Public ID</div>
                    <div class="detail-item-value mono" style="font-size:11px;word-break:break-all;color:var(--text-muted)">{{ $wallet->public_id }}</div>
                </div>

            </div>

            <div class="divider"></div>
            <p style="font-size:13px;color:var(--text-muted);margin:0">
                Demo identity only. Recovery phrases are never stored; the platform keeps a one-way hash to recognise a returning phrase.
            </p>
        </div>
    </div>


    <div class="card">
        <div class="card-header"><span class="card-title">Account Owner</span></div>
        <div class="card-body">
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-item-label">Username</div>
                    <div class="detail-item-value mono">{{ $wallet->user?->username ?? 'Not registered' }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Email</div>
                    <div class="detail-item-value">{{ $wallet->user?->email ?? 'Not registered' }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Full Name</div>
                    <div class="detail-item-value">{{ $wallet->user?->fullName() ?? 'Not registered' }}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-item-label">Verified</div>
                    <div class="detail-item-value">
                        @if($wallet->user?->email_verified_at)
                            <span class="badge badge-active">Yes</span>
                        @else
                            <span class="badge badge-pending">No</span>
                        @endif
                    </div>
                </div>
            </div>
            @if($wallet->user)
                <div class="divider"></div>
                <a href="{{ route('admin.users.show', $wallet->user) }}" class="btn btn-primary btn-sm">View Full Profile</a>
            @endif
        </div>
    </div>
</div>

<div class="card" style="margin-top:24px;border-color:rgba(220,38,38,0.2)">
    <div class="card-header"><span class="card-title" style="color:var(--red)">Danger Zone</span></div>
    <div class="card-body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:16px">
            Deleting this wallet record removes the wallet identity. The user account will remain active
            but will no longer be accessible through this wallet.
        </p>
        <form method="POST" action="{{ route('admin.wallets.destroy', $wallet) }}"
              onsubmit="return confirm('Permanently delete this wallet record? This cannot be undone.')">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Wallet Record</button>
        </form>
    </div>
</div>
@endsection
