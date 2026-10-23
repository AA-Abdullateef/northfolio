@extends('layouts.user')
@section('title', 'Swap')
@section('page-title', 'Swap Assets')

@section('content')
<div class="container-fluid">

    <div class="row mb-4">
        <div class="col-12 col-lg-7">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">New Swap</span>
                </div>
                <div class="card-body pt-0">

                    <div id="swap-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>
                    <div id="swap-form-success" class="alert alert-success py-2 px-3" style="font-size:13px; display:none;"></div>

                    <form id="swap-form">
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">From Asset</label>
                            <select id="swap-from-asset" class="form-control form-control-sm" required>
                                <option value="">Select asset to swap from</option>
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Amount</label>
                            <input type="number" step="any" min="0" id="swap-amount" class="form-control form-control-sm" placeholder="0.00" required>
                            <small class="text-muted" id="swap-balance-hint"></small>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">To Asset</label>
                            <select id="swap-to-asset" class="form-control form-control-sm" required>
                                <option value="">Select asset to receive</option>
                            </select>
                        </div>

                        <!-- Live rate preview -->
                        <div id="swap-preview" class="border rounded p-3 mb-3 bg-light" style="font-size:13px; display:none;">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Rate</span>
                                <span id="swap-preview-rate">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Charge (<span id="swap-preview-charge-pct">0</span>%)</span>
                                <span id="swap-preview-charge-amount" class="text-danger">—</span>
                            </div>
                            <div class="d-flex justify-content-between mb-1 border-top pt-2">
                                <span class="text-muted">Balance Required</span>
                                <span id="swap-preview-total-required" class="font-weight-bold text-dark">—</span>
                            </div>
                            <div class="d-flex justify-content-between font-weight-bold">
                                <span>You receive</span>
                                <span id="swap-preview-receive" class="text-success">—</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="swap-submit-btn">
                            Swap
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>

    <!-- Pending swaps -->
    <div class="row mb-4" id="swap-pending-wrapper" style="display:none;">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Pending Swaps</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size:12px;">
                                    <th class="py-2 pl-0">From</th>
                                    <th class="py-2">Amount</th>
                                    <th class="py-2">To</th>
                                    <th class="py-2">Receive</th>
                                    <th class="py-2 pr-0 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="swap-pending-table"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Swap history -->
    <div class="row" id="swap-history-wrapper" style="display:none;">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Swap History</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size:13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size:12px;">
                                    <th class="py-2 pl-0">From</th>
                                    <th class="py-2">Amount</th>
                                    <th class="py-2">To</th>
                                    <th class="py-2">Received</th>
                                    <th class="py-2 pr-0 text-end">Status</th>
                                </tr>
                            </thead>
                            <tbody id="swap-history-table"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@include('user.swap_modal')
@endsection
