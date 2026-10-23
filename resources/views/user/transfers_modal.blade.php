<script>
let selectedRecipientId = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchTransferOptions();

    document.getElementById('transfer-lookup-btn').addEventListener('click', lookupRecipient);
    document.getElementById('transfer-recipient-clear-btn').addEventListener('click', clearRecipient);
    document.getElementById('transfer-form').addEventListener('submit', submitTransferForm);
    document.getElementById('transfer-otp-form').addEventListener('submit', submitTransferOtp);
    document.getElementById('back-to-transfer-form-btn').addEventListener('click', showTransferFormView);
    document.getElementById('transfer-success-back-btn').addEventListener('click', () => {
        showTransferFormView();
        fetchTransferOptions();
    });
});

function authToken() {
    const token = localStorage.getItem('auth_token');
    if (!token) {
        window.location.href = "{{ route('wallet.view.generate') }}";
        return null;
    }
    return token;
}

function showTransferFormView() {
    document.getElementById('transfer-otp-view').style.display = 'none';
    document.getElementById('transfer-success-view').style.display = 'none';
    document.getElementById('transfer-form-view').style.display = 'block';
}

function showTransferOtpView() {
    document.getElementById('transfer-form-view').style.display = 'none';
    document.getElementById('transfer-success-view').style.display = 'none';
    document.getElementById('transfer-otp-view').style.display = 'block';
}

function showTransferSuccessView() {
    document.getElementById('transfer-form-view').style.display = 'none';
    document.getElementById('transfer-otp-view').style.display = 'none';
    document.getElementById('transfer-success-view').style.display = 'block';
}

async function fetchTransferOptions() {
    const token = authToken();
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/transfers/options') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        if (!result.success || !result.data) return;

        const assetSelect = document.getElementById('transfer-asset');
        assetSelect.innerHTML = '<option value="">Select an asset</option>';
        (result.data.assets || []).forEach(asset => {
            assetSelect.innerHTML += `<option value="${asset.id}">${asset.label || asset.name}</option>`;
        });

        renderPendingTransfers(result.data.pending || []);
    } catch (error) {
        console.error('Transfer options fetch failure:', error);
    }
}

function renderPendingTransfers(pending) {
    const wrapper = document.getElementById('pending-transfers-wrapper');
    const tableBody = document.getElementById('pending-transfers-table');

    if (!pending.length) {
        wrapper.style.display = 'none';
        return;
    }

    wrapper.style.display = 'block';
    tableBody.innerHTML = '';

    pending.forEach(tx => {
        tableBody.innerHTML += `
            <tr class="border-bottom-0">
                <td class="py-3 pl-0 font-weight-bold text-dark">${tx.asset?.label || tx.asset?.name || '—'}</td>
                <td class="py-3 text-secondary">${tx.amount}</td>
                <td class="py-3 pr-0 text-end"><span class="badge badge-warning text-capitalize px-2 py-1">${tx.status_label || tx.status}</span></td>
            </tr>
        `;
    });
}

// NOTE: assumes a GET /api/v1/wallet/lookup?query= endpoint returning
// { success, data: { wallet: { id, fingerprint } } } or success:false when no match is found.
async function lookupRecipient() {
    const token = authToken();
    if (!token) return;

    const query = document.getElementById('transfer-recipient-query').value.trim();
    const statusEl = document.getElementById('transfer-lookup-status');
    const lookupBtn = document.getElementById('transfer-lookup-btn');

    statusEl.textContent = '';
    statusEl.classList.remove('text-danger');

    if (!query) {
        statusEl.textContent = 'Enter a wallet fingerprint, public ID, or ID to search.';
        statusEl.classList.add('text-danger');
        return;
    }

    lookupBtn.disabled = true;
    lookupBtn.innerText = 'Searching...';

    try {
        const response = await fetch(`{{ url('api/v1/wallet/lookup') }}?query=${encodeURIComponent(query)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();

        if (!result.success || !result.data || !result.data.wallet) {
            statusEl.textContent = result.message || 'No wallet found matching that query.';
            statusEl.classList.add('text-danger');
            return;
        }

        const wallet = result.data.wallet;
        selectedRecipientId = wallet.id;

        document.getElementById('transfer-recipient-label').innerText = wallet.fingerprint || wallet.id;
        document.getElementById('transfer-recipient-found').style.display = 'flex';
        document.querySelector('.input-group').style.display = 'none';

        updateTransferSubmitState();
    } catch (error) {
        console.error('Recipient lookup failure:', error);
        statusEl.textContent = 'Something went wrong while searching. Please try again.';
        statusEl.classList.add('text-danger');
    } finally {
        lookupBtn.disabled = false;
        lookupBtn.innerText = 'Find';
    }
}

function clearRecipient() {
    selectedRecipientId = null;
    document.getElementById('transfer-recipient-query').value = '';
    document.getElementById('transfer-lookup-status').textContent = '';
    document.getElementById('transfer-recipient-found').style.display = 'none';
    document.querySelector('.input-group').style.display = 'flex';
    updateTransferSubmitState();
}

function updateTransferSubmitState() {
    const submitBtn = document.getElementById('transfer-submit-btn');
    const hint = document.getElementById('transfer-submit-hint');

    submitBtn.disabled = !selectedRecipientId;
    hint.style.display = selectedRecipientId ? 'none' : 'block';
}

async function submitTransferForm(event) {
    event.preventDefault();

    const token = authToken();
    if (!token) return;

    if (!selectedRecipientId) {
        return;
    }

    const payload = {
        asset_id: document.getElementById('transfer-asset').value,
        amount: document.getElementById('transfer-amount').value,
        recipient_id: selectedRecipientId
    };

    const alertBox = document.getElementById('transfer-form-alert');
    const submitBtn = document.getElementById('transfer-submit-btn');

    alertBox.style.display = 'none';

    if (!payload.asset_id || !payload.amount) {
        alertBox.textContent = 'Please select an asset and enter an amount.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Submitting...';

    try {
        const response = await fetch("{{ url('api/v1/transfers') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Unable to start this transfer right now.';
            alertBox.style.display = 'block';
            return;
        }

        if (result.data && result.data.requires_confirmation) {
            pendingTransferPayload = payload;
            document.getElementById('transfer-otp-token').value = '';
            showTransferOtpView();
            return;
        }

        showTransferSuccessView();
    } catch (error) {
        console.error('Transfer submission failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Continue';
    }
}

let pendingTransferPayload = null;

async function submitTransferOtp(event) {
    event.preventDefault();

    const token = authToken();
    if (!token || !pendingTransferPayload) return;

    const otpToken = document.getElementById('transfer-otp-token').value;
    const alertBox = document.getElementById('transfer-otp-alert');
    const submitBtn = document.getElementById('transfer-otp-submit-btn');

    alertBox.style.display = 'none';

    if (!otpToken) {
        alertBox.textContent = 'Please enter the confirmation code sent to your email.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Confirming...';

    try {
        const response = await fetch("{{ url('api/v1/transfers/confirm') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ ...pendingTransferPayload, token: otpToken })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid or expired confirmation code.';
            alertBox.style.display = 'block';
            return;
        }

        pendingTransferPayload = null;
        showTransferSuccessView();
    } catch (error) {
        console.error('Transfer confirmation failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Confirm Transfer';
    }
}
</script>