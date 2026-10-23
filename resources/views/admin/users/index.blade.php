@extends('layouts.admin')

@section('title', 'Users')
@section('page-title', 'Users')

@section('topbar-actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">+ New User</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">All Customers</span>
        <form method="GET" action="{{ route('admin.users.index') }}">
            <div class="search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search users...">
            </div>
        </form>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Country</th>
                    <th>Status</th>
                    <th>2FA</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <div style="font-weight:500">{{ $user->username }}</div>
                        <div class="td-muted">{{ $user->email }}</div>
                    </td>
                    <td class="td-muted">{{ $user->country }}</td>
                    <td>
                        @if($user->email_verified_at)
                            <span class="badge badge-active">Verified</span>
                        @else
                            <span class="badge badge-pending">Unverified</span>
                        @endif
                    </td>
                    <td>
                        @if($user->two_factor)
                            <span class="badge badge-active">On</span>
                        @else
                            <span class="badge badge-cancelled">Off</span>
                        @endif
                    </td>
                    <td class="td-muted">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.users.show', $user) }}" class="btn btn-ghost btn-sm">View</a>
                            <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" style="text-align:center;color:var(--text-faint);padding:32px">No users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div style="padding:0 20px">
        <div>
            {{ $users->withQueryString()->links() }}
        </div>
    </div>
    @endif
</div>
@endsection