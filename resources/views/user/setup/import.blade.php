@extends('layouts.setup')

@section('title', 'Import Existing Wallet')

@section('content')
<div class="setup-card">
    <h2>Import Wallet</h2>
    <p>Enter your 12-word secret recovery phrase to restore your wallet profile.</p>

    <form id="import-form">
        <div class="form-group">
            <label class="form-label" for="mnemonic">Recovery Phrase (Mnemonic)</label>
            <textarea 
                id="mnemonic" 
                name="mnemonic" 
                class="form-control" 
                rows="3" 
                placeholder="word1 word2 word3..." 
                required
            ></textarea>
        </div>

        <button type="submit" class="btn-login">Restore Wallet</button>
    </form>
</div>

<script>
document.getElementById('import-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const mnemonicValue = document.getElementById('mnemonic').value.trim();
    
    try {
        const response = await fetch('/api/v1/wallet/import', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ mnemonic: mnemonicValue })
        });

        const result = await response.json();

        if (result.success) {
            localStorage.setItem('auth_token', result.data.access_token);
            window.location.href = "/dashboard";
        } else {
            alert('Import failed: ' + result.message);
        }
    } catch (error) {
        console.error('Import process exception:', error);
    }
});
</script>
@endsection
