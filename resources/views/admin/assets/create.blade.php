@extends('layouts.admin')
@section('title', 'New Asset')
@section('page-title', 'New Asset')
@section('topbar-actions')
    <a href="{{ route('admin.assets.index') }}" class="btn btn-ghost btn-sm">Back to Assets</a>
@endsection

@section('content')
<div class="card" style="max-width:520px">
    <div class="card-header"><span class="card-title">Create Asset</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('admin.assets.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Slug <span style="color:var(--text-faint)">(lowercase, e.g. btc)</span></label>
                <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="usd">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Display Label</label>
                <input type="text" name="label" class="form-control" value="{{ old('label') }}" placeholder="US Dollar">
                @error('label')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Icon filename <span style="color:var(--text-faint)">(e.g. btc.png)</span></label>
                <input type="text" name="icon" class="form-control" value="{{ old('icon') }}" placeholder="btc.png">
                @error('icon')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Price Source</label>
                <select name="price_source" class="form-control">
                    <option value="">None</option>
                    <option value="manual" @selected(old('price_source') === 'manual')>Manual</option>
                    <option value="coingecko" @selected(old('price_source') === 'coingecko')>CoinGecko</option>
                    <option value="alphavantage" @selected(old('price_source') === 'alphavantage')>Alpha Vantage</option>
                    <option value="finnhub" @selected(old('price_source') === 'finnhub')>Finnhub</option>
                </select>
                @error('price_source')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Price Source ID</label>
                <input type="text" name="price_source_id" class="form-control" value="{{ old('price_source_id') }}" placeholder="bitcoin or AAPL">
                @error('price_source_id')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Manual USD Price</label>
                <input type="number" step="0.00000001" min="0" name="current_price" class="form-control" value="{{ old('current_price') }}" placeholder="1.00000000">
                @error('current_price')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                    <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                    <span class="form-label" style="margin:0">Active</span>
                </label>
            </div>

            <hr style="margin: 20px 0;">
            <p class="form-label" style="font-weight:700; margin-bottom:12px;">Charge Rates (%)</p>

            <div class="form-group">
                <label class="form-label">Swap Charge Rate</label>
                <input type="number" step="0.01" min="0" max="100" name="swap_charge_rate" class="form-control" value="{{ old('swap_charge_rate') }}" placeholder="e.g. 1.50">
                <small style="color:var(--text-muted);">Deducted from the source asset. Leave blank for no charge.</small>
                @error('swap_charge_rate')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Buy Charge Rate</label>
                <input type="number" step="0.01" min="0" max="100" name="buy_charge_rate" class="form-control" value="{{ old('buy_charge_rate') }}" placeholder="e.g. 2.00">
                <small style="color:var(--text-muted);">Deducted from the fiat amount sent by the buyer. Leave blank for no charge.</small>
                @error('buy_charge_rate')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Sell Charge Rate</label>
                <input type="number" step="0.01" min="0" max="100" name="sell_charge_rate" class="form-control" value="{{ old('sell_charge_rate') }}" placeholder="e.g. 2.00">
                <small style="color:var(--text-muted);">Deducted from the gross fiat payout. Leave blank for no charge.</small>
                @error('sell_charge_rate')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit" class="btn btn-primary">Create Asset</button>
        </form>
    </div>
</div>
@endsection
