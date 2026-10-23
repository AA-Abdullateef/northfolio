<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LedgerService;

class DashboardController extends Controller
{
    public function __construct(private readonly LedgerService $ledger) {}

    public function index()
    {
        $totals = $this->ledger->platformTotals();

        $recentTransactions = Transaction::with('wallet.user.profile', 'asset')
            ->where('status', TransactionStatus::Completed->value)
            ->latest()
            ->take(10)
            ->get();

        $totalUsers         = User::customers()->count();
        $pendingDeposits    = Transaction::where('type', TransactionType::Deposit->value)
                                         ->where('status', TransactionStatus::Pending->value)
                                         ->count();
        $pendingWithdrawals = Transaction::where('type', TransactionType::Withdrawal->value)
                                         ->where('status', TransactionStatus::Pending->value)
                                         ->count();

        return view('admin.dashboard.index', compact(
            'totals',
            'recentTransactions',
            'totalUsers',
            'pendingDeposits',
            'pendingWithdrawals',
        ));
    }
}
