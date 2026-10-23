<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Exceptions\InvalidTransactionTransition;
use App\Services\TransactionLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionLifecycleService $lifecycle
    ) {}

    public function index(Request $request)
    {
        $query = Transaction::with([
            'wallet.user.profile',
            'asset',
            'subMethod',
        ])->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;

            $query->whereHas('wallet.user', fn ($q) =>
                $q->where('email', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
            );
        }

        return view('admin.transactions.index', [
            'transactions' => $query->paginate(25)->withQueryString(),
            'assets' => Asset::active()->get(),
        ]);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load([
            'wallet.user.profile',
            'asset',
            'subMethod',
        ]);

        return view('admin.transactions.show', compact('transaction'));
    }

    public function complete(Transaction $transaction)
    {
        try {
            $this->lifecycle->complete($transaction, TransactionLifecycleService::ACTOR_ADMIN, (string) auth()->id());
        } catch (InvalidTransactionTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction completed.');
    }

    public function cancel(Transaction $transaction)
    {
        try {
            $this->lifecycle->cancel($transaction, TransactionLifecycleService::ACTOR_ADMIN, (string) auth()->id());
        } catch (InvalidTransactionTransition $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction cancelled.');
    }
}
