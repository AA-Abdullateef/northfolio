<script>
let withdrawalMethodsData = [];
let pendingWithdrawalPayload = null;

document.addEventListener('DOMContentLoaded', () => {
    fetchWithdrawalOptions();

    document.getElementById('withdrawal-method').addEventListener('change', populateWithdrawalSubMethodSelect);
    document.getElementById('withdrawal-form').addEventListener('submit', submitWithdrawalForm);
    document.getElementById('withdrawal-otp-form').addEventListener('submit', submitWithdrawalOtp);
    document.getElementById('back-to-withdrawal-form-btn').addEventListener('click', showWithdrawalFormView);
    document.getElementById('withdrawal-success-back-btn').addEventListener('click', () => {
        showWithdrawalFormView();
        fetchWithdrawalOptions();
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

function showWithdrawalFormView() {
    document.getElementById('withdrawal-otp-view').style.display = 'none';
    document.getElementById('withdrawal-success-view').style.display = 'none';
    document.getElementById('withdrawal-form-view').style.display = 'block';
}

function showWithdrawalOtpView() {
    document.getElementById('withdrawal-form-view').style.display = 'none';
    document.getElementById('withdrawal-success-view').style.display = 'none';
    document.getElementById('withdrawal-otp-view').style.display = 'block';
}

function showWithdrawalSuccessView() {
    document.getElementById('withdrawal-form-view').style.display = 'none';
    document.getElementById('withdrawal-otp-view').style.display = 'none';
    document.getElementById('withdrawal-success-view').style.display = 'block';
}

async function fetchWithdrawalOptions() {
    const token = authToken();
    if (!token) return;

    try {
        const response = await fetch("{{ url('api/v1/withdrawals/options') }}", {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer ' + token
            }
        });

        const result = await response.json();
        if (!result.success || !result.data) return;

        const assetSelect = document.getElementById('withdrawal-asset');
        assetSelect.innerHTML = '<option value="">Select an asset</option>';
        (result.data.assets || []).forEach(asset => {
            assetSelect.innerHTML += `<option value="${asset.id}">${asset.label || asset.name}</option>`;
        });

        withdrawalMethodsData = result.data.methods || [];
        const methodSelect = document.getElementById('withdrawal-method');
        methodSelect.innerHTML = '<option value="">Select a method</option>';
        withdrawalMethodsData.forEach(method => {
            methodSelect.innerHTML += `<option value="${method.id}">${method.name}</option>`;
        });

        renderWithdrawalTable('pending-withdrawals-wrapper', 'pending-withdrawals-table', result.data.pending || []);
        renderWithdrawalTable('withdrawal-history-wrapper', 'withdrawal-history-table', result.data.history || []);
    } catch (error) {
        console.error('Withdrawal options fetch failure:', error);
    }
}

function renderWithdrawalTable(wrapperId, tableId, rows) {
    const wrapper = document.getElementById(wrapperId);
    const tableBody = document.getElementById(tableId);

    if (!rows.length) {
        wrapper.style.display = 'none';
        return;
    }

    wrapper.style.display = 'block';
    tableBody.innerHTML = '';

    rows.forEach(tx => {
        let badgeClass = 'badge-secondary';
        const statusStr = (tx.status || '').toLowerCase();
        if (statusStr === 'pending') badgeClass = 'badge-warning';
        if (statusStr === 'completed' || statusStr === 'success') badgeClass = 'badge-success';
        if (statusStr === 'cancelled' || statusStr === 'failed') badgeClass = 'badge-danger';

        tableBody.innerHTML += `
            <tr class="border-bottom-0">
                <td class="py-3 pl-0 font-weight-bold text-dark">${tx.asset?.label || tx.asset?.name || '—'}</td>
                <td class="py-3 text-secondary">${tx.amount}</td>
                <td class="py-3 text-muted" style="font-size:11px;">${tx.reference || '—'}</td>
                <td class="py-3 pr-0 text-end"><span class="badge ${badgeClass} text-capitalize px-2 py-1">${tx.status_label || tx.status}</span></td>
            </tr>
        `;
    });
}

function populateWithdrawalSubMethodSelect() {
    const methodId = document.getElementById('withdrawal-method').value;
    const subMethodSelect = document.getElementById('withdrawal-sub-method');

    subMethodSelect.innerHTML = '';
    subMethodSelect.disabled = true;

    const method = withdrawalMethodsData.find(m => m.id === methodId);
    const subMethods = (method?.sub_methods || []).filter(sm => sm.is_active);

    if (!subMethods.length) {
        subMethodSelect.innerHTML = '<option value="">No active channels for this method</option>';
        return;
    }

    subMethodSelect.innerHTML = '<option value="">Select a channel</option>';
    subMethods.forEach(sm => {
        subMethodSelect.innerHTML += `<option value="${sm.id}">${sm.name}</option>`;
    });
    subMethodSelect.disabled = false;
}

function collectWithdrawalPayload() {
    return {
        asset_id: document.getElementById('withdrawal-asset').value,
        sub_method_id: document.getElementById('withdrawal-sub-method').value,
        reference: document.getElementById('withdrawal-reference').value,
        amount: document.getElementById('withdrawal-amount').value
    };
}

async function submitWithdrawalForm(event) {
    event.preventDefault();

    const token = authToken();
    if (!token) return;

    const payload = collectWithdrawalPayload();
    const alertBox = document.getElementById('withdrawal-form-alert');
    const submitBtn = document.getElementById('withdrawal-submit-btn');

    alertBox.style.display = 'none';

    if (!payload.asset_id || !payload.sub_method_id || !payload.reference || !payload.amount) {
        alertBox.textContent = 'Please fill in every field before continuing.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Submitting...';

    try {
        const response = await fetch("{{ url('api/v1/withdrawals') }}", {
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
            alertBox.textContent = result.message || 'Unable to start this withdrawal right now.';
            alertBox.style.display = 'block';
            return;
        }

        if (result.data && result.data.requires_confirmation) {
            // Two-factor is on for this wallet's user — keep the form values to resend on confirm.
            pendingWithdrawalPayload = payload;
            document.getElementById('withdrawal-otp-token').value = '';
            showWithdrawalOtpView();
            return;
        }

        showWithdrawalSuccessView();
    } catch (error) {
        console.error('Withdrawal submission failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Continue';
    }
}

async function submitWithdrawalOtp(event) {
    event.preventDefault();

    const token = authToken();
    if (!token || !pendingWithdrawalPayload) return;

    const otpToken = document.getElementById('withdrawal-otp-token').value;
    const alertBox = document.getElementById('withdrawal-otp-alert');
    const submitBtn = document.getElementById('withdrawal-otp-submit-btn');

    alertBox.style.display = 'none';

    if (!otpToken) {
        alertBox.textContent = 'Please enter the confirmation code sent to your email.';
        alertBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerText = 'Confirming...';

    try {
        const response = await fetch("{{ url('api/v1/withdrawals/confirm') }}", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token
            },
            body: JSON.stringify({ ...pendingWithdrawalPayload, token: otpToken })
        });

        const result = await response.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Invalid or expired confirmation code.';
            alertBox.style.display = 'block';
            return;
        }

        pendingWithdrawalPayload = null;
        showWithdrawalSuccessView();
    } catch (error) {
        console.error('Withdrawal confirmation failure:', error);
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerText = 'Confirm Withdrawal';
    }
}
</script>