@extends('layouts.user')
@section('title', 'Withdraw')
@section('page-title', 'Withdraw Funds')

@section('content')
<div class="container-fluid">

    <!-- Step 1: New withdrawal form -->
    <div id="withdrawal-form-view">
        <div class="row">
            <div class="col-12 col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">New Withdrawal</span>
                    </div>
                    <div class="card-body pt-0">

                        <div id="withdrawal-form-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>

                        <form id="withdrawal-form">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Asset</label>
                                <select id="withdrawal-asset" class="form-control form-control-sm" required>
                                    <option value="">Select an asset</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Withdrawal Method</label>
                                <select id="withdrawal-method" class="form-control form-control-sm" required>
                                    <option value="">Select a method</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Channel</label>
                                <select id="withdrawal-sub-method" class="form-control form-control-sm" required disabled>
                                    <option value="">Select a withdrawal method first</option>
                                </select>
                            </div>

                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Destination (wallet address / account)</label>
                                <input type="text" id="withdrawal-reference" class="form-control form-control-sm" placeholder="Destination wallet address or account" required minlength="9">
                            </div>

                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Amount</label>
                                <input type="number" step="any" min="0" id="withdrawal-amount" class="form-control form-control-sm" placeholder="0.00" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="withdrawal-submit-btn">
                                Continue
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>

        <!-- Pending withdrawals -->
        <div class="row mt-4" id="pending-withdrawals-wrapper" style="display:none;">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Pending Withdrawals</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 12px;">
                                        <th class="py-2 pl-0">Asset</th>
                                        <th class="py-2">Amount</th>
                                        <th class="py-2">Destination</th>
                                        <th class="py-2 pr-0 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="pending-withdrawals-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Withdrawal history -->
        <div class="row mt-4" id="withdrawal-history-wrapper" style="display:none;">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Recent Withdrawals</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 12px;">
                                        <th class="py-2 pl-0">Asset</th>
                                        <th class="py-2">Amount</th>
                                        <th class="py-2">Destination</th>
                                        <th class="py-2 pr-0 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="withdrawal-history-table"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: OTP confirmation (only shown when the wallet's user has 2FA enabled) -->
    <div id="withdrawal-otp-view" style="display:none;">
        <button type="button" class="btn btn-link p-0 mb-3 font-weight-bold text-secondary" id="back-to-withdrawal-form-btn" style="font-size:13px; text-decoration:none;">
            &larr; Back
        </button>

        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Confirm Withdrawal</span>
                    </div>
                    <div class="card-body pt-0">
                        <p class="small text-muted mb-3">
                            We've emailed a confirmation code to verify this withdrawal. Enter it below to proceed.
                        </p>

                        <div id="withdrawal-otp-alert" class="alert alert-danger py-2 px-3" style="font-size:13px; display:none;"></div>

                        <form id="withdrawal-otp-form">
                            <div class="form-group mb-3">
                                <label class="small font-weight-bold text-muted mb-1">Confirmation Code</label>
                                <input type="text" id="withdrawal-otp-token" class="form-control form-control-sm" placeholder="Enter code" required>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm font-weight-bold" id="withdrawal-otp-submit-btn">
                                Confirm Withdrawal
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Success -->
    <div id="withdrawal-success-view" style="display:none;">
        <div class="row">
            <div class="col-12 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body text-center py-5">
                        <h5 class="font-weight-bold text-success mb-2">Withdrawal Initiated</h5>
                        <p class="text-muted small mb-4">Your withdrawal is now pending review.</p>
                        <button type="button" class="btn btn-outline-dark btn-sm font-weight-bold" id="withdrawal-success-back-btn">
                            Back to Withdrawals
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@include('user.withdrawals_modal')

@endsection