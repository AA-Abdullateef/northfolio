@extends('layouts.user')
@section('title', 'Dashboard')
@section('page-title', 'Overview')
@section('page-sub', 'Your balances, demo wallet identity and latest ledger entries.')

@section('content')
<section class="hero">
    <div class="hero-main">
        <div class="hero-label">Total balance · USD value</div>
        <div class="hero-figure" id="wallet-balance">—</div>
        <div class="asset-chips" id="asset-badges-container"><!-- Appended dynamically via javascript --></div>
    </div>
    <div class="hero-side">
        <div class="hero-label">Quick actions</div>
        <div class="quick">
            <a href="{{ route('user.deposits.index') }}" class="btn btn-sm">Deposit</a>
            <a href="{{ route('user.transfers.index') }}" class="btn btn-sm">Transfer</a>
            <a href="{{ route('user.swap.index') }}" class="btn btn-sm">Swap</a>
            <a href="{{ route('user.withdrawals.index') }}" class="btn btn-sm">Withdraw</a>
        </div>
        <button id="logout-btn" type="button" class="hero-out">Sign out of this device</button>
    </div>
</section>

<div id="dashboard-content">
    <div class="card mb-4">
        <div class="card-header"><span class="card-title">Demo wallet ID</span></div>
        <div class="card-body">
            <dl class="idlist">
                <div><dt>Wallet ID</dt><dd id="wallet-id">Loading...</dd></div>
                <div><dt>Public reference</dt><dd id="wallet-public-key">Loading...</dd></div>
                <div><dt>Fingerprint</dt><dd id="wallet-fingerprint">Loading...</dd></div>
            </dl>
            <p class="small text-muted" style="margin:14px 0 0">
                <strong>Demo identity.</strong> This is not a real blockchain wallet. Your recovery phrase is never stored by {{ config('platform.company_short') }} — keep your own copy to sign in again.
            </p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="card-title">Recent activity</span>
            <a href="{{ route('user.transactions.index') }}" class="btn btn-link btn-sm">View all →</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="pl-0">Transaction ID</th>
                            <th>Asset</th>
                            <th>Action</th>
                            <th>Amount</th>
                            <th class="pr-0">Status</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-log-table">
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">Loading ledger entries...</td>
                        </tr>
                    </tbody>
                </table>
                <div id="empty-transactions-alert" class="text-center py-4 text-muted" style="opacity: 0; height: 0; overflow: hidden; padding: 0 !important; margin: 0 !important; transition: opacity 0.2s ease;">
                    No transactions yet. Start with a deposit.
                </div>
            </div>
        </div>
    </div>
</div>

@include('user.dashboard_modal')

@endsection
