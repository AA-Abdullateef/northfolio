<script>
let swapAssetsData = [];

document.addEventListener('DOMContentLoaded', () => {
    fetchSwapOptions();

    document.getElementById('swap-from-asset').addEventListener('change', updateSwapPreview);
    document.getElementById('swap-to-asset').addEventListener('change', updateSwapPreview);
    document.getElementById('swap-amount').addEventListener('input', updateSwapPreview);
    document.getElementById('swap-form').addEventListener('submit', submitSwap);
});

function authToken() {
    const token = localStorage.getItem('auth_token');
    if (!token) { window.location.href = "{{ route('wallet.view.generate') }}"; return null; }
    return token;
}

async function fetchSwapOptions() {
    const token = authToken(); if (!token) return;

    try {
        const res = await fetch("{{ url('api/v1/swap/options') }}", {
            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }
        });
        const result = await res.json();
        if (!result.success || !result.data) return;

        swapAssetsData = result.data.assets || [];

        const fromSel = document.getElementById('swap-from-asset');
        const toSel   = document.getElementById('swap-to-asset');
        fromSel.innerHTML = '<option value="">Select asset to swap from</option>';
        toSel.innerHTML   = '<option value="">Select asset to receive</option>';

        swapAssetsData.forEach(asset => {
            const label = `${asset.label} (${asset.name.toUpperCase()}) — $${Number(asset.price_usd).toLocaleString()}`;
            fromSel.innerHTML += `<option value="${asset.id}">${label}</option>`;
            toSel.innerHTML   += `<option value="${asset.id}">${label}</option>`;
        });

        renderSwapTable('swap-pending-wrapper', 'swap-pending-table', result.data.pending || [], true);
        renderSwapTable('swap-history-wrapper', 'swap-history-table', result.data.history || [], false);
    } catch (e) { console.error('Swap options error:', e); }
}

function getAsset(id) { return swapAssetsData.find(a => a.id === id) || null; }

function updateSwapPreview() {
    const fromId   = document.getElementById('swap-from-asset').value;
    const toId     = document.getElementById('swap-to-asset').value;
    const amount   = parseFloat(document.getElementById('swap-amount').value) || 0;
    const preview  = document.getElementById('swap-preview');
    const hint     = document.getElementById('swap-balance-hint');

    if (!fromId || !toId || fromId === toId || amount <= 0) {
        preview.style.display = 'none'; hint.textContent = ''; return;
    }

    const fromAsset = getAsset(fromId);
    const toAsset   = getAsset(toId);
    if (!fromAsset || !toAsset) { preview.style.display = 'none'; return; }

    const chargeRate    = parseFloat(fromAsset.swap_charge_rate) || 0;
    const chargeAmount  = parseFloat((amount * (chargeRate / 100)).toFixed(8));
    const totalRequired = parseFloat((amount + chargeAmount).toFixed(8));
    const fromPriceUsd  = parseFloat(fromAsset.price_usd);
    const toPriceUsd    = parseFloat(toAsset.price_usd);

    // Full amount (not net-of-charge) converts to target asset.
    const toAmount = parseFloat((amount * fromPriceUsd / toPriceUsd).toFixed(8));

    document.getElementById('swap-preview-rate').textContent =
        `1 ${fromAsset.name.toUpperCase()} = ${(fromPriceUsd / toPriceUsd).toFixed(8)} ${toAsset.name.toUpperCase()}`;
    document.getElementById('swap-preview-charge-pct').textContent   = chargeRate;
    document.getElementById('swap-preview-charge-amount').textContent =
        `${chargeAmount.toFixed(8)} ${fromAsset.name.toUpperCase()}`;
    document.getElementById('swap-preview-total-required').textContent =
        `${totalRequired} ${fromAsset.name.toUpperCase()}`;
    document.getElementById('swap-preview-receive').textContent =
        `${toAmount.toFixed(8)} ${toAsset.name.toUpperCase()}`;

    hint.textContent =
        `Balance required: ${totalRequired} ${fromAsset.name.toUpperCase()} `
        + `(${amount} swap + ${chargeAmount} charge)`;

    preview.style.display = 'block';
}

async function submitSwap(e) {
    e.preventDefault();
    const token = authToken(); if (!token) return;

    const alertBox  = document.getElementById('swap-form-alert');
    const successBox = document.getElementById('swap-form-success');
    const submitBtn = document.getElementById('swap-submit-btn');

    alertBox.style.display = 'none';
    successBox.style.display = 'none';

    const payload = {
        from_asset_id: document.getElementById('swap-from-asset').value,
        to_asset_id:   document.getElementById('swap-to-asset').value,
        amount:        document.getElementById('swap-amount').value,
    };

    if (!payload.from_asset_id || !payload.to_asset_id || !payload.amount) {
        alertBox.textContent = 'Please fill in all fields.';
        alertBox.style.display = 'block'; return;
    }

    if (payload.from_asset_id === payload.to_asset_id) {
        alertBox.textContent = 'Source and target asset must be different.';
        alertBox.style.display = 'block'; return;
    }

    submitBtn.disabled = true; submitBtn.textContent = 'Submitting...';

    try {
        const res = await fetch("{{ url('api/v1/swaps') }}", {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Swap failed.';
            alertBox.style.display = 'block'; return;
        }

        successBox.textContent = result.message || 'Swap initiated. Pending admin approval.';
        successBox.style.display = 'block';
        document.getElementById('swap-form').reset();
        document.getElementById('swap-preview').style.display = 'none';
        fetchSwapOptions();
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false; submitBtn.textContent = 'Swap';
    }
}

function renderSwapTable(wrapperId, tableId, rows, isPending) {
    const wrapper = document.getElementById(wrapperId);
    const tbody   = document.getElementById(tableId);
    if (!rows.length) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = 'block';
    tbody.innerHTML = '';
    rows.forEach(tx => {
        const meta       = tx.meta || {};
        const chargeAmount = meta.charge_amount ? `${parseFloat(meta.charge_amount).toFixed(5)} ${tx.asset?.name?.toUpperCase() || ''}` : '—';
        const toAmount   = meta.to_amount  ? parseFloat(meta.to_amount).toFixed(5)  : '—';
        const toLabel    = meta.to_asset_label || '—';
        let badgeClass   = 'badge-warning';
        if (tx.status === 'completed') badgeClass = 'badge-success';
        if (tx.status === 'cancelled') badgeClass = 'badge-danger';
        tbody.innerHTML += `
            <tr class="border-bottom-0">
                <td class="py-3 pl-0 font-weight-bold">${tx.asset?.label || '—'}</td>
                <td class="py-3 text-secondary">${parseFloat(tx.amount).toFixed(5)}</td>
                <td class="py-3 text-muted">${toLabel}</td>
                <td class="py-3">${toAmount}</td>
                <td class="py-3 pr-0 text-end"><span class="badge ${badgeClass} px-2 py-1">${tx.status_label || tx.status}</span></td>
            </tr>`;
    });
}
</script>
