@extends('layouts.admin')
@section('title', 'Assets')
@section('page-title', 'Assets')
@section('topbar-actions')
    <div class="flex gap-2">
        <form method="POST" action="{{ route('admin.assets.sync-prices') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">Sync Prices</button>
        </form>
        <a href="{{ route('admin.assets.create') }}" class="btn btn-primary btn-sm">New Asset</a>
    </div>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Asset Catalog</span></div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Label</th><th>Icon</th><th>Price</th><th>Source</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($assets as $asset)
                <tr>
                    <td class="td-mono">{{ $asset->name }}</td>
                    <td style="font-weight:500">{{ $asset->label }}</td>
                    <td class="td-muted">{{ $asset->icon ?? 'None' }}</td>
                    <td class="td-muted">
                        {{ $asset->current_price ? '$' . number_format((float) $asset->current_price, 8) : 'Unpriced' }}
                    </td>
                    <td class="td-muted">{{ $asset->price_source ?? 'None' }}</td>
                    <td>
                        <span class="badge {{ $asset->active ? 'badge-active' : 'badge-cancelled' }}">
                            {{ $asset->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.assets.edit', $asset) }}" class="btn btn-ghost btn-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete {{ $asset->label }}?')">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;color:var(--text-faint);padding:32px">No assets found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($assets->hasPages())
    <div style="padding:0 20px">{{ $assets->links() }}</div>
    @endif
</div>
@endsection
