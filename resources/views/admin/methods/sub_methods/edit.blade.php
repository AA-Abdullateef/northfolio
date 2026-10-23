@extends('layouts.admin')
@section('title', 'Edit Sub-Method')
@section('page-title', 'Edit Sub-Method')
@section('topbar-actions')
    <a href="{{ route('admin.methods.edit', $method) }}" class="btn btn-ghost btn-sm">← {{ $method->name }}</a>
@endsection

@section('content')
<div class="card" style="max-width:680px">
    <div class="card-header"><span class="card-title">Edit — {{ $subMethod->name }}</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.methods.sub-methods.update', [$method, $subMethod]) }}">
            @csrf @method('PUT')
            <div class="grid-2">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $subMethod->name) }}">
                    @error('name')<div class="form-error">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Wallet Address</label>
                    <input type="text" name="wallet_address" class="form-control" value="{{ old('wallet_address', $subMethod->wallet_address) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Network</label>
                    <input type="text" name="network" class="form-control" value="{{ old('network', $subMethod->network) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Name</label>
                    <input type="text" name="account_name" class="form-control" value="{{ old('account_name', $subMethod->account_name) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" class="form-control" value="{{ old('account_number', $subMethod->account_number) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" value="{{ old('bank_name', $subMethod->bank_name) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Routing Number</label>
                    <input type="text" name="routing_number" class="form-control" value="{{ old('routing_number', $subMethod->routing_number) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">SWIFT / BIC</label>
                    <input type="text" name="swift_code" class="form-control" value="{{ old('swift_code', $subMethod->swift_code) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">IBAN</label>
                    <input type="text" name="iban" class="form-control" value="{{ old('iban', $subMethod->iban) }}">
                </div>
                <div class="form-group" style="grid-column:span 2">
                    <label class="form-label">Instructions</label>
                    <textarea name="instructions" class="form-control" rows="3">{{ old('instructions', $subMethod->instructions) }}</textarea>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $subMethod->is_active) ? 'checked' : '' }}>
                        <span class="form-label" style="margin:0">Active</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('admin.methods.edit', $method) }}" class="btn btn-ghost">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection