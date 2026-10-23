<?php

namespace App\Http\Controllers\API\V1;

use App\Enums\TransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\TransactionResource;
use App\Exceptions\InvalidTransactionTransition;
use App\Models\Transaction;
use App\Services\TransactionLifecycleService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly TransactionLifecycleService $lifecycle) {}

    /**
     * Paginated list of the authenticated wallet's transactions.
     * Optionally filter by type: deposit | withdrawal | transfer.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with('asset')->where('wallet_id', $request->wallet()->id)->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $transactions = $query->paginate($perPage);

        return $this->paginated(
            'Transactions.',
            $transactions,
            fn ($t) => new TransactionResource($t)
        );
    }

    /**
     * Wallets may only cancel their own pending transactions.
     */
    public function updateStatus(Request $request, Transaction $transaction): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:cancelled'],
        ]);

        if ($transaction->wallet_id !== $request->wallet()->id) {
            return $this->forbidden();
        }

        try {
            $this->lifecycle->cancel($transaction, TransactionLifecycleService::ACTOR_USER, $request->wallet()->id);
        } catch (InvalidTransactionTransition $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success('Transaction cancelled.', [
            'transaction' => new TransactionResource($transaction->fresh('asset')),
        ]);
    }
}
