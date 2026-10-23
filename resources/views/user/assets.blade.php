@extends('layouts.user')
@section('title', 'Assets')
@section('page-title', 'Asset Overview')

@section('content')
<div class="container-fluid">

    <!-- Asset List View -->
    <div id="assets-list-view">
        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0">
                        <span class="card-title font-weight-bold text-dark">Wallet Asset Balances</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 12px;">
                                        <th class="py-2 pl-0">Asset</th>
                                        <th class="py-2">Balance</th>
                                        <th class="py-2 pr-0 text-end">Activity</th>
                                    </tr>
                                </thead>
                                <tbody id="assets-overview-table">
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">Loading assets...</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div id="empty-assets-alert" class="text-center py-5 text-muted" style="display:none;">
                                No active assets configured on this platform yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Asset Detail View (per-asset transaction drilldown) -->
    <div id="asset-detail-view" style="display:none;">
        <button type="button" class="btn btn-link p-0 mb-3 font-weight-bold text-secondary" id="back-to-assets-list-btn" style="font-size:13px; text-decoration:none;">
            &larr; Back to assets
        </button>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-transparent py-3 border-0 d-flex justify-content-between align-items-center">
                        <span class="card-title font-weight-bold text-dark" id="asset-detail-name">—</span>
                        <span class="h5 mb-0 font-weight-bold text-success" id="asset-detail-balance">0.00</span>
                    </div>
                    <div class="card-body pt-0">
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0" style="font-size: 13px;">
                                <thead>
                                    <tr class="text-muted border-bottom" style="font-size: 12px;">
                                        <th class="py-2 pl-0">Transaction ID</th>
                                        <th class="py-2">Action</th>
                                        <th class="py-2">Amount</th>
                                        <th class="py-2 pr-0">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="asset-transactions-table">
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Loading transactions...</td>
                                    </tr>
                                </tbody>
                            </table>
                            <div id="empty-asset-transactions-alert" class="text-center py-5 text-muted" style="display:none;">
                                No transactions recorded for this asset yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

@include('user.assets_modal')

@endsection