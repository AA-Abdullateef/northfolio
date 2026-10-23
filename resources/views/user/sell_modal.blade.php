<script>
let sellAssetsData   = [];
let sellMethodsData  = [];
let sellBalancesData = {};

document.addEventListener('DOMContentLoaded', () => {
    fetchSellOptions();
    document.getElementById('sell-asset').addEventListener('change', onSellAssetChange);
    document.getElementById('sell-amount').addEventListener('input', updateSellPreview);
    document.getElementById('sell-method').addEventListener('change', populateSellSubMethod);
    document.getElementById('sell-form').addEventListener('submit', submitSell);
});

function authToken() {
    const token = localStorage.getItem('auth_token');
    if (!token) { window.location.href = "{{ route('wallet.view.generate') }}"; return null; }
    return token;
}

async function fetchSellOptions() {
    const token = authToken(); if (!token) return;

    try {
        const res = await fetch("{{ url('api/v1/sell/options') }}", {
            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }
        });
        const result = await res.json();
        if (!result.success || !result.data) return;

        // assets from sell/options includes { asset: {...}, balance: float }
        const assetsWithBalance = result.data.assets || [];
        sellAssetsData  = assetsWithBalance.map(row => row.asset);
        sellMethodsData = result.data.methods || [];

        assetsWithBalance.forEach(row => {
            sellBalancesData[row.asset.id] = row.balance;
        });

        const assetSel = document.getElementById('sell-asset');
        assetSel.innerHTML = '<option value="">Select an asset</option>';
        sellAssetsData.forEach(asset => {
            const bal = parseFloat(sellBalancesData[asset.id] || 0).toFixed(5);
            assetSel.innerHTML += `<option value="${asset.id}">${asset.label} (${asset.name.toUpperCase()}) — Balance: ${bal}</option>`;
        });

        const methodSel = document.getElementById('sell-method');
        methodSel.innerHTML = '<option value="">Select a method</option>';
        sellMethodsData.forEach(m => {
            methodSel.innerHTML += `<option value="${m.id}">${m.name}</option>`;
        });

        renderSellTable('sell-pending-wrapper', 'sell-pending-table', result.data.pending || []);
        renderSellTable('sell-history-wrapper', 'sell-history-table', result.data.history || []);
    } catch (e) { console.error('Sell options error:', e); }
}

function onSellAssetChange() {
    const assetId = document.getElementById('sell-asset').value;
    const hint    = document.getElementById('sell-balance-hint');
    if (assetId && sellBalancesData[assetId] !== undefined) {
        const bal = parseFloat(sellBalancesData[assetId]).toFixed(5);
        const asset = sellAssetsData.find(a => a.id === assetId);
        hint.textContent = `Available: ${bal} ${asset?.name?.toUpperCase() || ''}`;
    } else {
        hint.textContent = '';
    }
    updateSellPreview();
}

function updateSellPreview() {
    const assetId = document.getElementById('sell-asset').value;
    const amount  = parseFloat(document.getElementById('sell-amount').value) || 0;
    const preview = document.getElementById('sell-preview');

    if (!assetId || amount <= 0) { preview.style.display = 'none'; return; }

    const asset = sellAssetsData.find(a => a.id === assetId);
    if (!asset || !asset.price_usd) { preview.style.display = 'none'; return; }

    const priceUsd   = parseFloat(asset.price_usd);
    const grossFiat  = amount * priceUsd;
    const chargeRate = parseFloat(asset.sell_charge_rate) || 0;
    const chargeFiat = grossFiat * (chargeRate / 100);
    const netFiat    = grossFiat - chargeFiat;

    document.getElementById('sell-preview-price').textContent      = `$${priceUsd.toLocaleString()} / ${asset.name.toUpperCase()}`;
    document.getElementById('sell-preview-gross').textContent      = `$${grossFiat.toFixed(2)}`;
    document.getElementById('sell-preview-charge-pct').textContent = chargeRate;
    document.getElementById('sell-preview-charge').textContent     = `$${chargeFiat.toFixed(2)}`;
    document.getElementById('sell-preview-net').textContent        = `$${netFiat.toFixed(2)}`;
    preview.style.display = 'block';
}

function populateSellSubMethod() {
    const methodId   = document.getElementById('sell-method').value;
    const subSel     = document.getElementById('sell-sub-method');
    subSel.innerHTML = ''; subSel.disabled = true;

    const method     = sellMethodsData.find(m => m.id === methodId);
    const subMethods = (method?.sub_methods || []).filter(sm => sm.is_active);

    if (!subMethods.length) {
        subSel.innerHTML = '<option value="">No channels available</option>'; return;
    }
    subSel.innerHTML = '<option value="">Select a channel</option>';
    subMethods.forEach(sm => { subSel.innerHTML += `<option value="${sm.id}">${sm.name}</option>`; });
    subSel.disabled = false;
}

async function submitSell(e) {
    e.preventDefault();
    const token = authToken(); if (!token) return;

    const alertBox   = document.getElementById('sell-form-alert');
    const successBox = document.getElementById('sell-form-success');
    const submitBtn  = document.getElementById('sell-submit-btn');

    alertBox.style.display = 'none'; successBox.style.display = 'none';

    const payload = {
        asset_id:      document.getElementById('sell-asset').value,
        amount:        document.getElementById('sell-amount').value,
        sub_method_id: document.getElementById('sell-sub-method').value || null,
        reference:     document.getElementById('sell-reference').value || null,
    };

    if (!payload.asset_id || !payload.amount) {
        alertBox.textContent = 'Please select an asset and enter the amount.';
        alertBox.style.display = 'block'; return;
    }

    submitBtn.disabled = true; submitBtn.textContent = 'Placing order...';

    try {
        const res = await fetch("{{ url('api/v1/sells') }}", {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify(payload)
        });
        const result = await res.json();

        if (!result.success) {
            alertBox.textContent = result.message || 'Sell order failed.';
            alertBox.style.display = 'block'; return;
        }

        successBox.textContent = result.message || 'Sell order placed. Pending admin approval.';
        successBox.style.display = 'block';
        document.getElementById('sell-form').reset();
        document.getElementById('sell-preview').style.display = 'none';
        fetchSellOptions();
    } catch (err) {
        alertBox.textContent = 'Something went wrong. Please try again.';
        alertBox.style.display = 'block';
    } finally {
        submitBtn.disabled = false; submitBtn.textContent = 'Place Sell Order';
    }
}

function renderSellTable(wrapperId, tableId, rows) {
    const wrapper = document.getElementById(wrapperId);
    const tbody   = document.getElementById(tableId);
    if (!rows.length) { wrapper.style.display = 'none'; return; }
    wrapper.style.display = 'block'; tbody.innerHTML = '';
    rows.forEach(tx => {
        const meta = tx.meta || {};
        let badgeClass = 'badge-warning';
        if (tx.status === 'completed') badgeClass = 'badge-success';
        if (tx.status === 'cancelled') badgeClass = 'badge-danger';
        tbody.innerHTML += `
            <tr class="border-bottom-0">
                <td class="py-3 pl-0 font-weight-bold">${tx.asset?.label || '—'}</td>
                <td class="py-3 text-secondary">${parseFloat(tx.amount).toFixed(5)} ${tx.asset?.name?.toUpperCase() || ''}</td>
                <td class="py-3 text-success">$${meta.net_fiat ? parseFloat(meta.net_fiat).toFixed(2) : '—'}</td>
                <td class="py-3 pr-0 text-end"><span class="badge ${badgeClass} px-2 py-1">${tx.status_label || tx.status}</span></td>
            </tr>`;
    });
}
</script>
