@extends('layouts.setup')

@section('title', 'Verify Recovery Phrase')

@section('content')
<div class="setup-card">
    <h2>Confirm Secret Phrase</h2>
    <p>Please select or type the requested words from your mnemonic phrase to verify you backed it up correctly.</p>

    <div id="loading-challenge">Loading setup data...</div>

    <form id="challenge-form" style="display: none;">
        <div id="questions-container">
            <!-- Dynamic Form fields populated via script -->
        </div>
        
        <div id="error-alert" class="notice notice-error" style="display:none;"></div>

        <button type="submit" class="btn-login">Verify & Finalize Wallet</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    // Matches the exact key name saved in step 1
    const token = sessionStorage.getItem('wallet_setup_token'); 
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return;
    }

    try {
        const response = await fetch("{{ url('api/v1/wallet/challenge') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ setup_token: token })
        });

        const result = await response.json();

        if (result.success && result.data && result.data.positions) {
            document.getElementById('loading-challenge').style.display = 'none';
            const form = document.getElementById('challenge-form');
            const container = document.getElementById('questions-container');
            
            form.style.display = 'block';
            container.innerHTML = '';
            
            // Generates input fields dynamically for whatever positions your backend returns
            result.data.positions.forEach(position => {
                container.innerHTML += `
                    <div class="form-group">
                        <label class="form-label">What is word number <strong>#${position}</strong>?</label>
                        <input type="text" name="word_${position}" class="form-control" data-position="${position}" required autocomplete="off" style="text-transform: lowercase;">
                    </div>
                `;
            });
        } else {
            alert(result.message || 'Challenge generation failed.');
            window.location.href = "{{ route('wallet.view.generate') }}";
        }
    } catch (error) {
        console.error('Challenge network layer exception:', error);
        document.getElementById('loading-challenge').innerText = 'Connection error. Please reload the page.';
    }
});

document.getElementById('challenge-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const token = sessionStorage.getItem('wallet_setup_token');
    const inputs = document.querySelectorAll('#questions-container input');
    const errorAlert = document.getElementById('error-alert');
    
    errorAlert.style.display = 'none';
    
    const wordsPayload = {};
    inputs.forEach(input => {
        wordsPayload[input.dataset.position] = input.value.trim().toLowerCase();
    });

    try {
        const response = await fetch("{{ url('api/v1/wallet/verify') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                setup_token: token,
                words: wordsPayload
            })
        });

        const result = await response.json();

        if (result.success) {
            // Success cleanup and redirect using named web routes helper
            sessionStorage.removeItem('wallet_setup_token');
            localStorage.setItem('auth_token', result.data.access_token);
            
            window.location.href = "{{ route('dashboard') }}"; 
        } else {
            // Render specific backend validation error feedback directly inside the card profile
            errorAlert.innerText = result.message || 'Verification failed. Please review your words.';
            errorAlert.style.display = 'block';
        }
    } catch (error) {
        console.error('Verification engine execution crash:', error);
        errorAlert.innerText = 'Network error during submission. Try again.';
        errorAlert.style.display = 'block';
    }
});
</script>
@endsection
