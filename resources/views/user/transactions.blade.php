@extends('layouts.user')
@section('title', 'Transactions')
@section('page-title', 'Transactions History')

@section('content')
<div class="container-fluid">
    
    <!-- Top Filter Row Bar Matrix (Renders Instantly) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="d-flex justify-content-end mb-3" style="gap: 8px;">
                <a href="{{ route('user.deposits.index') }}" class="btn btn-primary btn-sm font-weight-bold">
                    + New Deposit
                </a>
                <a href="{{ route('user.withdrawals.index') }}" class="btn btn-outline-dark btn-sm font-weight-bold">
                    + New Withdrawal
                </a>
                <a href="{{ route('user.transfers.index') }}" class="btn btn-outline-dark btn-sm font-weight-bold">
                    + New Transfer
                </a>
            </div>
            <form id="filter-form" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="small font-weight-bold text-muted mb-1">Filter by Execution Type</label>
                    <select id="filter-type" class="form-control form-control-sm">
                        <option value="">All Transfer Actions</option>
                        <option value="deposit">Deposits</option>
                        <option value="withdrawal">Withdrawals</option>
                        <option value="transfer">Internal Transfers</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="small font-weight-bold text-muted mb-1">Filter by Status State</label>
                    <select id="filter-status" class="form-control form-control-sm">
                        <option value="">All Status Conditions</option>
                        <option value="pending">Pending Verification</option>
                        <option value="completed">Completed / Settled</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="failed">Failed / Rejected</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end" style="margin-top: auto;">
                    <button type="submit" class="btn btn-primary btn-sm w-100 font-weight-bold" style="height: 31px;">🔄 Apply Filters</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Transaction Ledger Dataset Sheet -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-transparent py-3 border-0">
                    <span class="card-title font-weight-bold text-dark">Transaction Ledger Journal Logs</span>
                </div>
                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                            <thead>
                                <tr class="text-muted border-bottom" style="font-size: 12px;">
                                    <th class="py-2 pl-0">Transaction ID</th>
                                    <th class="py-2">Asset Type</th>
                                    <th class="py-2">Execution Action</th>
                                    <th class="py-2">Amount Metric</th>
                                    <th class="py-2">Status Reference</th>
                                    <th class="py-2 pr-0 text-end">Settlement Management</th>
                                </tr>
                            </thead>
                            <tbody id="full-transactions-table-body">
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">
                                        Loading historical tracking ledger lines...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="empty-ledger-alert" class="text-center py-5 text-muted" style="display:none;">
                            No historical ledger logs match your criteria on this cryptographic security core.
                        </div>
                    </div>

                    <!-- Dynamic Pagination Navigation Section Module Matrix (Always Visible) -->
                    <div id="pagination-controls-wrapper" class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                        <button id="prev-page-btn" class="btn btn-outline-secondary btn-sm font-weight-bold" disabled>◀ Previous Page</button>
                        <span id="pagination-info-txt" class="text-muted small">Page 1 of 1</span>
                        <button id="next-page-btn" class="btn btn-outline-secondary btn-sm font-weight-bold" disabled>Next Page ▶</button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

@include('user.transactions_modal') 

@endsection