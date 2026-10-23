@extends('layouts.user')
@section('title', 'Buy')
@section('page-title', 'Buy Assets')

@section('content')
<div class="container-fluid">

    <div class="alert alert-info py-2 px-3 mb-4" style="font-size:13px;">
        <strong>Demo exchange.</strong> Buys are internal ledger trades funded from your USD balance
        (<strong id="usd-balance">$0.00</strong>). Prices come from a public market-data feed; nothing is bought on a real market.
    </div>

    <div class="row mb-4">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Buy with USD</span>
                </div>
                <div class="card-body pt-0">
                    <div id="buy-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="buy-form-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <form id="buy-form">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Asset to buy</label>
                            <select id="buy-asset" class="form-control form-control-sm" required>
                                <option value="">Select an asset</option>
                            </select>
                        </div>
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Spend (USD)</label>
                            <input type="number" step="0.01" min="0.01" id="buy-usd-amount" class="form-control form-control-sm" placeholder="0.00" required>
                        </div>

                        <div id="buy-preview" class="border rounded p-3 mb-3 bg-light" style="font-size:13px; display:none;">
                            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Price</span><span id="buy-preview-price">—</span></div>
                            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Charge (<span id="buy-preview-charge-pct">0</span>%)</span><span id="buy-preview-charge" class="text-danger">—</span></div>
                            <div class="d-flex justify-content-between font-weight-bold"><span>You receive</span><span id="buy-preview-receive" class="text-success">—</span></div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="buy-submit-btn">Buy now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row" id="buy-history-wrapper" style="display:none;">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Buy History</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size:12px;">
                                    <th class="py-2 pl-0">Asset</th>
                                    <th class="py-2">USD spent</th>
                                    <th class="py-2">Received</th>
                                    <th class="py-2 pr-0 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="buy-history-table"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let buyAssetsData = [];
const $ = id => document.getElementById(id);

function authToken() {
    const t = localStorage.getItem('auth_token');
    if (!t) { window.location.href = "{{ route('wallet.view.generate') }}"; return null; }
    return t;
}
const call = (path, opts = {}) => fetch("{{ url('api/v1') }}/" + path, {
    ...opts,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken() },
}).then(r => r.json());

document.addEventListener('DOMContentLoaded', () => {
    loadBuyOptions();
    $('buy-asset').addEventListener('change', updatePreview);
    $('buy-usd-amount').addEventListener('input', updatePreview);
    $('buy-form').addEventListener('submit', submitBuy);
});

async function loadBuyOptions() {
    const res = await call('buy/options');
    if (!res.success) return;
    buyAssetsData = res.data.assets || [];
    $('usd-balance').textContent = '$' + Number(res.data.usd_balance || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
    const sel = $('buy-asset');
    sel.innerHTML = '<option value="">Select an asset</option>' + buyAssetsData.map(a =>
        `<option value="${a.id}">${a.label} (${a.name.toUpperCase()}) — $${Number(a.price_usd).toLocaleString()}</option>`).join('');
    renderHistory(res.data.history || []);
}

function updatePreview() {
    const asset = buyAssetsData.find(a => a.id === $('buy-asset').value);
    const usd = parseFloat($('buy-usd-amount').value) || 0;
    if (!asset || !asset.price_usd || usd <= 0) { $('buy-preview').style.display = 'none'; return; }
    const rate = parseFloat(asset.buy_charge_rate) || 0;
    const charge = Math.round(usd * rate) / 100;
    const receive = (usd - charge) / parseFloat(asset.price_usd);
    $('buy-preview-price').textContent = `$${Number(asset.price_usd).toLocaleString()} / ${asset.name.toUpperCase()}`;
    $('buy-preview-charge-pct').textContent = rate;
    $('buy-preview-charge').textContent = `$${charge.toFixed(2)}`;
    $('buy-preview-receive').textContent = `${receive.toFixed(8)} ${asset.name.toUpperCase()}`;
    $('buy-preview').style.display = 'block';
}

async function submitBuy(e) {
    e.preventDefault();
    const alertBox = $('buy-form-alert'), ok = $('buy-form-success'), btn = $('buy-submit-btn');
    alertBox.style.display = ok.style.display = 'none';
    btn.disabled = true;
    const res = await call('buys', { method: 'POST', body: JSON.stringify({
        asset_id: $('buy-asset').value, usd_amount: parseFloat($('buy-usd-amount').value),
    }) });
    btn.disabled = false;
    if (!res.success) { alertBox.textContent = res.message || 'Buy failed.'; alertBox.style.display = 'block'; return; }
    ok.textContent = res.message; ok.style.display = 'block';
    $('buy-form').reset(); $('buy-preview').style.display = 'none';
    loadBuyOptions();
}

function renderHistory(rows) {
    $('buy-history-wrapper').style.display = rows.length ? 'block' : 'none';
    $('buy-history-table').innerHTML = rows.map(tx => {
        const m = tx.meta || {};
        return `<tr class="border-bottom-0">
            <td class="py-3 pl-0 font-weight-bold">${tx.asset?.label || '—'}</td>
            <td class="py-3 text-secondary">$${m.usd_amount ? parseFloat(m.usd_amount).toFixed(2) : '—'}</td>
            <td class="py-3">${parseFloat(tx.amount).toFixed(8)} ${tx.asset?.name?.toUpperCase() || ''}</td>
            <td class="py-3 pr-0 text-end"><span class="badge badge-success px-2 py-1">${tx.status_label || tx.status}</span></td>
        </tr>`;
    }).join('');
}
</script>
@endsection
