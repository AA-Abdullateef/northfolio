@extends('layouts.user')
@section('title', 'Sell')
@section('page-title', 'Sell Crypto')

@section('content')
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Sell Crypto</span>
                </div>
                <div class="card-body pt-0">

                    <div id="sell-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="sell-form-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <form id="sell-form">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Asset to Sell</label>
                            <select id="sell-asset" class="form-control form-control-sm" required>
                                <option value="">Select an asset</option>
                            </select>
                            <small class="text-muted" id="sell-balance-hint"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Amount to Sell</label>
                            <input type="number" step="any" min="0" id="sell-amount" class="form-control form-control-sm" placeholder="0.00" required>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Payment Method</label>
                            <select id="sell-method" class="form-control form-control-sm">
                                <option value="">Select a method</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Channel</label>
                            <select id="sell-sub-method" class="form-control form-control-sm" disabled>
                                <option value="">Select a payment method first</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Payout Account / Address</label>
                            <input type="text" id="sell-reference" class="form-control form-control-sm" placeholder="Bank account or wallet address">
                        </div>

                        <!-- Live preview -->
                        <div id="sell-preview" class="border rounded p-3 mb-3 bg-light" style="font-size:13px; display:none;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Price</span>
                                <span id="sell-preview-price">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Gross Payout</span>
                                <span id="sell-preview-gross">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Charge (<span id="sell-preview-charge-pct">0</span>%)</span>
                                <span id="sell-preview-charge" class="text-danger">—</span>
                            </div>
                            <div class="d-flex justify-content-between font-weight-bold">
                                <span>Net Payout</span>
                                <span id="sell-preview-net" class="text-success">—</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="sell-submit-btn">
                            Place Sell Order
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending sell orders -->
    <div class="row mb-4" id="sell-pending-wrapper" style="display:none;">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Pending Sell Orders</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size:12px;">
                                    <th class="py-2 pl-0">Asset</th>
                                    <th class="py-2">Amount</th>
                                    <th class="py-2">Net Payout</th>
                                    <th class="py-2 pr-0 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="sell-pending-table"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sell history -->
    <div class="row" id="sell-history-wrapper" style="display:none;">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Sell History</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size:12px;">
                                    <th class="py-2 pl-0">Asset</th>
                                    <th class="py-2">Amount Sold</th>
                                    <th class="py-2">Net Payout</th>
                                    <th class="py-2 pr-0 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="sell-history-table"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@include('user.sell_modal')
@endsection
