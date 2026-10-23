@extends('layouts.user')
@section('title', 'Demo Wallet ID')
@section('page-title', 'Demo Wallet ID')

@section('content')
<div class="container-fluid">
    <div class="alert alert-info py-2 px-3 mb-4" style="font-size:13px;">
        <strong>This is a demo identity, not a real blockchain wallet.</strong>
        Your 12-word phrase signs you in to Northfolio only. It controls no on-chain funds —
        never reuse a real wallet's recovery phrase here.
    </div>

    <div class="row">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Your Demo Wallet ID</span>
                </div>
                <div class="card-body pt-0" style="font-size:13px;">
                    <div class="mb-3">
                        <div class="small font-weight-bold text-muted mb-1">Fingerprint</div>
                        <code id="wallet-fingerprint" style="word-break:break-all;">Loading…</code>
                    </div>
                    <div class="mb-3">
                        <div class="small font-weight-bold text-muted mb-1">Public ID (share this to receive transfers)</div>
                        <code id="wallet-public-key" style="word-break:break-all;">Loading…</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) { window.location.href = "{{ route('wallet.view.generate') }}"; return; }
    const res = await fetch("{{ url('api/v1/me') }}", { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token } });
    const body = await res.json();
    const w = body.data && body.data.wallet;
    document.getElementById('wallet-fingerprint').textContent = w ? w.fingerprint : '—';
    document.getElementById('wallet-public-key').textContent = w ? w.public_id : '—';
});
</script>
@endsection
