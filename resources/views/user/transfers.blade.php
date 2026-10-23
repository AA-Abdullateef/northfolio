@extends('layouts.user')
@section('title', 'Transfer')
@section('page-title', 'Transfer Funds')

@section('content')
<div class="container-fluid">

    <!-- Step 1: New transfer form -->
    <div id="transfer-form-view">
        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">New Transfer</span>
                    </div>
                    <div class="card-body pt-0">

                        <div id="transfer-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>

                        <!-- Recipient lookup -->
                        <div class="form-group mb-3">
                            <label class="small font-weight-bold text-muted mb-1">Recipient</label>
                            <div class="input-group input-group-sm">
                                <input type="text" id="transfer-recipient-query" class="form-control form-control-sm" placeholder="Wallet fingerprint, public ID, or ID">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold" id="transfer-lookup-btn">
                                        Find
                                    </button>
                                </div>
                            </div>
                            <small class="text-muted" id="transfer-lookup-status"></small>

                            <div id="transfer-recipient-found" class="d-flex justify-content-between align-items-center border rounded p-2 mt-2" style="display:none; font-size:13px;">
                                <span>Sending to <strong id="transfer-recipient-label">—</strong></span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" id="transfer-recipient-clear-btn">Change</button>
                            </div>
                        </div>

                        <form id="transfer-form">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Asset</label>
                                <select id="transfer-asset" class="form-control form-control-sm" required>
                                    <option value="">Select an asset</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Amount</label>
                                <input type="number" step="any" min="0" id="transfer-amount" class="form-control form-control-sm" placeholder="0.00" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="transfer-submit-btn" disabled>
                                Continue
                            </button>
                            <small class="text-muted d-block mt-2" id="transfer-submit-hint">Find a recipient before continuing.</small>
                        </form>

                    </div>
                </div>
            </div>
        </div>

        <!-- Pending transfers -->
        <div class="row mt-4" id="pending-transfers-wrapper" style="display:none;">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Pending Transfers</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 12px;">
                                        <th class="py-2 pl-0">Asset</th>
                                        <th class="py-2">Amount</th>
                                        <th class="py-2 pr-0 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="pending-transfers-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: OTP confirmation (only when the wallet's user has 2FA enabled) -->
    <div id="transfer-otp-view" style="display:none;">
        <button type="button" class="btn btn-link p-0 mb-3 font-weight-bold text-secondary" id="back-to-transfer-form-btn" style="font-size:13px; text-decoration:none;">
            &larr; Back
        </button>

        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Confirm Transfer</span>
                    </div>
                    <div class="card-body pt-0">
                        <p class="small text-muted mb-3">
                            We've emailed a confirmation code to verify this transfer. Enter it below to proceed.
                        </p>

                        <div id="transfer-otp-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>

                        <form id="transfer-otp-form">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Confirmation Code</label>
                                <input type="text" id="transfer-otp-token" class="form-control form-control-sm" placeholder="Enter code" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="transfer-otp-submit-btn">
                                Confirm Transfer
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Success -->
    <div id="transfer-success-view" style="display:none;">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <h5 class="font-weight-bold text-success mb-2">Transfer Initiated</h5>
                        <p class="text-muted small mb-4">Your transfer is now pending review.</p>
                        <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold" id="transfer-success-back-btn">
                            Back to Transfers
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@include('user.transfers_modal')

@endsection