@extends('layouts.user')
@section('title', 'Deposit')
@section('page-title', 'Deposit USD')

@section('content')
<div class="container-fluid">

    <div class="alert alert-info py-2 px-3 mb-4" style="font-size:13px;">
        <strong>Demo / test mode.</strong> Payments run through Stripe's <em>test</em> environment — use card
        <code>4242 4242 4242 4242</code>, any future expiry and any CVC. No real money moves.
        Your balance is credited only when Stripe's signed webhook confirms the payment.
    </div>

    <div id="deposit-start-view">
        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">New Deposit</span>
                    </div>
                    <div class="card-body pt-0">
                        <div id="deposit-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                        <form id="deposit-form">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Amount (USD)</label>
                                <input type="number" step="0.01" min="1" id="deposit-amount" class="form-control form-control-sm" placeholder="100.00" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="deposit-submit-btn">Continue to payment</button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm border-0" id="pending-deposits-wrapper" style="display:none;">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Pending deposit</span>
                    </div>
                    <div class="card-body pt-0">
                        <p class="small text-muted mb-2">Awaiting confirmation from Stripe. This page updates automatically.</p>
                        <div id="pending-deposits-list" style="font-size:13px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="deposit-pay-view" style="display:none;">
        <button type="button" class="btn btn-link p-0 mb-3 font-weight-bold text-secondary" id="back-btn" style="font-size:13px; text-decoration:none;">&larr; Back</button>
        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Pay $<span id="pay-amount">0.00</span> with Stripe (test mode)</span>
                    </div>
                    <div class="card-body pt-0">
                        <div id="pay-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                        <div id="pay-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>
                        <form id="payment-form">
                            <div id="payment-element" class="mb-3"></div>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="pay-btn">Pay now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
let stripe = null, elements = null, publishableKey = null, activeTxId = null, pollTimer = null;

function authToken() {
    const t = localStorage.getItem('auth_token');
    if (!t) { window.location.href = "{{ route('wallet.view.generate') }}"; return null; }
    return t;
}
const $ = id => document.getElementById(id);
const api = (path, opts = {}) => fetch("{{ url('api/v1') }}/" + path, {
    ...opts,
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + authToken() },
}).then(r => r.json());

function show(view) {
    $('deposit-start-view').style.display = view === 'start' ? 'block' : 'none';
    $('deposit-pay-view').style.display   = view === 'pay'   ? 'block' : 'none';
}
function flash(id, msg) { const el = $(id); el.textContent = msg; el.style.display = msg ? 'block' : 'none'; }

document.addEventListener('DOMContentLoaded', async () => {
    $('deposit-form').addEventListener('submit', startDeposit);
    $('payment-form').addEventListener('submit', confirmPayment);
    $('back-btn').addEventListener('click', () => { clearInterval(pollTimer); show('start'); loadOptions(); });
    await loadOptions();
});

async function loadOptions() {
    const res = await api('deposits/options');
    if (!res.success) return;
    publishableKey = res.data.stripe_publishable_key;
    const pending = res.data.pending || [];
    $('pending-deposits-wrapper').style.display = pending.length ? 'block' : 'none';
    $('pending-deposits-list').innerHTML = pending.map(t =>
        `<div class="d-flex justify-content-between border-bottom py-2"><span>$${parseFloat(t.amount).toFixed(2)}</span><span class="text-warning">Pending</span></div>`).join('');
    if (pending.length) pollList();
}

function pollList() {
    clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        const res = await api('deposits/options');
        if (res.success && !(res.data.pending || []).length) { clearInterval(pollTimer); loadOptions(); }
    }, 3000);
}

async function startDeposit(e) {
    e.preventDefault();
    flash('deposit-form-alert', '');
    if (!publishableKey || publishableKey.includes('xxx')) {
        return flash('deposit-form-alert', 'Stripe test keys are not configured (set STRIPE_PUBLISHABLE_KEY / STRIPE_SECRET_KEY).');
    }
    const btn = $('deposit-submit-btn'); btn.disabled = true;
    const res = await api('deposits', { method: 'POST', body: JSON.stringify({ usd_amount: parseFloat($('deposit-amount').value) }) });
    btn.disabled = false;
    if (!res.success) {
        const first = res.errors ? Object.values(res.errors)[0][0] : null;
        return flash('deposit-form-alert', first || res.message || 'Could not start deposit.');
    }
    activeTxId = res.data.transaction.id;
    $('pay-amount').textContent = parseFloat(res.data.transaction.amount).toFixed(2);
    stripe = Stripe(publishableKey);
    elements = stripe.elements({ clientSecret: res.data.client_secret });
    $('payment-element').innerHTML = '';
    elements.create('payment').mount('#payment-element');
    flash('pay-alert', ''); flash('pay-success', '');
    show('pay');
}

async function confirmPayment(e) {
    e.preventDefault();
    flash('pay-alert', '');
    $('pay-btn').disabled = true;
    const { error } = await stripe.confirmPayment({ elements, redirect: 'if_required', confirmParams: { return_url: window.location.href } });
    if (error) { $('pay-btn').disabled = false; return flash('pay-alert', error.message); }
    flash('pay-success', 'Payment submitted. Waiting for Stripe to confirm…');
    pollTx();
}

function pollTx() {
    clearInterval(pollTimer);
    pollTimer = setInterval(async () => {
        const res = await api('deposits/' + activeTxId);
        const status = res.data && res.data.transaction && res.data.transaction.status;
        if (status === 'completed') {
            clearInterval(pollTimer);
            flash('pay-success', 'Deposit confirmed — your USD balance has been credited.');
            $('pay-btn').style.display = 'none';
        } else if (status === 'cancelled') {
            clearInterval(pollTimer);
            flash('pay-success', ''); flash('pay-alert', 'Payment failed or was cancelled.');
            $('pay-btn').disabled = false;
        }
    }, 2000);
}
</script>
@endsection
