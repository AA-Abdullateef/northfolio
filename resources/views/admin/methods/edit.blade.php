@extends('layouts.admin')
@section('title', 'Edit Method')
@section('page-title', 'Edit — ' . $method->name)
@section('topbar-actions')
    <a href="{{ route('admin.methods.index') }}" class="btn btn-ghost btn-sm">← Methods</a>
@endsection

@section('content')
<div class="grid-2 mb-6">

    {{-- Edit method name --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Method Details</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.methods.update', $method) }}">
                @csrf @method('PUT')
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $method->name) }}">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <a href="{{ route('admin.methods.index') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Add sub-method --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Add Sub-Method</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.methods.sub-methods.store', $method) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Coinbase BTC">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Wallet Address</label>
                    <input type="text" name="wallet_address" class="form-control" value="{{ old('wallet_address') }}" placeholder="bc1q...">
                </div>
                <div class="form-group">
                    <label class="form-label">Network</label>
                    <input type="text" name="network" class="form-control" value="{{ old('network') }}" placeholder="BTC, ERC-20, TRC-20...">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Name</label>
                    <input type="text" name="account_name" class="form-control" value="{{ old('account_name') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" class="form-control" value="{{ old('account_number') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Routing Number</label>
                    <input type="text" name="routing_number" class="form-control" value="{{ old('routing_number') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">SWIFT / BIC</label>
                    <input type="text" name="swift_code" class="form-control" value="{{ old('swift_code') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="iban" class="form-control" value="{{ old('iban') }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" class="form-control" rows="3" placeholder="Optional payment instructions...">{{ old('instructions') }}</textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <span class="form-label" style="margin:0">Active</span>
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Add Sub-Method</button>
            </form>
        </div>
    </div>
</div>

{{-- Existing sub-methods --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Sub-Methods ({{ $method->subMethods->count() }})</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Wallet / Account</th><th>Network / Bank</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($method->subMethods as $sub)
                <tr>
                    <td style="font-weight:500">{{ $sub->name }}</td>
                    <td class="td-mono" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                        {{ $sub->wallet_address ?? $sub->account_number ?? '—' }}
                    </td>
                    <td class="td-muted">{{ $sub->network ?? $sub->bank_name ?? '—' }}</td>
                    <td>
                        <span class="badge {{ $sub->is_active ? 'badge-active' : 'badge-cancelled' }}">
                            {{ $sub->is_active ? 'Active' : 'Off' }}
                        </span>
                    </td>
                    <td>
                        <div class="flex gap-2">
                            <a href="{{ route('admin.methods.sub-methods.edit', [$method, $sub]) }}" class="btn btn-ghost btn-sm">Edit</a>
                            <form method="POST" action="{{ route('admin.methods.sub-methods.destroy', [$method, $sub]) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm" onclick="return confirm('Delete {{ $sub->name }}?')">Del</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center;color:var(--text-faint);padding:24px">No sub-methods yet. Add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
