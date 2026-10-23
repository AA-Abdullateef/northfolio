@extends('layouts.setup')

@section('title', 'Finalizing Wallet')

@section('content')
<div class="setup-card center">
    <h2>Processing Verification...</h2>
    <p>Checking security sequence structures against key clusters.</p>
    <div class="spinner"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = sessionStorage.getItem('wallet_setup_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
    } else {
        window.location.href = "{{ route('wallet.view.challenge') }}";
    }
});
</script>
@endsection
