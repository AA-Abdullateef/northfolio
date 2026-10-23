<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\API\V1\AssetResource;
use App\Http\Resources\API\V1\TransactionResource;
use App\Http\Resources\API\V1\UserResource;
use App\Http\Resources\API\V1\MethodResource;
use App\Models\Asset;
use App\Models\Method;
use App\Models\Transaction;
use App\Services\LedgerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly LedgerService $ledger) {}

    /**
     * Main dashboard — profile, per-asset balances, recent transactions.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $wallet = $request->wallet();

        if (! $wallet instanceof \App\Models\Wallet) {
            return $this->error('Unrecognized wallet identity credentials.');
        }

    $assetId = $request->query('asset_id');

    // Now these lines will execute perfectly without throwing PHP errors!
    $query = Transaction::with(['asset','subMethod'])->where('wallet_id', $wallet->id)->latest()->take(20);
    if ($assetId) {
        $query->where('asset_id', $assetId);
    }

    $recent = $query->get();
    $assets = Asset::active()->get();
    $balances = $this->ledger->allBalancesFor($wallet->id);
    $portfolioUsd = $this->ledger->totalUsdBalanceFor($wallet->id);
    $assetBalances = $assets->map(fn (Asset $asset) => [
        'asset' => new AssetResource($asset),
        'balance' => $balances[$asset->id] ?? \App\Support\Money::zero(),
    ])->values();

    return $this->success('Dashboard data.', [
        'assets'   => AssetResource::collection($assets),
        'balance' => $portfolioUsd['total_usd'],
        'balances' => $balances,
        'asset_balances' => $assetBalances,
        'portfolio_usd' => $portfolioUsd,
        'recent'   => TransactionResource::collection($recent),
        'wallet'   => [
            'id'          => $wallet->id,
            'public_id'  => $wallet->public_id,
            'fingerprint' => $wallet->fingerprint,
        ]
    ]);
}

    /**
     * All assets with current balances.
     */
    public function assetsOverview(Request $request): JsonResponse
    {
        $wallet   = $request->wallet();
        $assets = Asset::active()->get();

        $data = $assets->map(fn (Asset $a) => [
            'asset'   => new AssetResource($a),
            'balance' => $this->ledger->balanceFor($wallet->id, $a->id),
        ]);

        return $this->success('Assets overview.', ['assets' => $data]);
    }

    /**
     * Per-asset transaction history.
     */
    public function assetTransactions(Request $request, string $assetId): JsonResponse
    {
        $wallet = $request->wallet();

        $transactions = Transaction::where('wallet_id', $wallet->id)
                                   ->where('asset_id', $assetId)
                                   ->with('asset')
                                   ->latest()
                                   ->take(50)
                                   ->get();

        return $this->success('Asset transactions.', [
            'asset_id'     => $assetId,
            'balance'      => $this->ledger->balanceFor($wallet->id, $assetId),
            'transactions' => TransactionResource::collection($transactions),
        ]);
    }

    /**
     * Deposit preflight — assets + methods + pending deposits.
     */
    public function depositOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();

        $pending = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->with('asset')
            ->latest()
            ->get();

        $usd = Asset::whereRaw('lower(name) = ?', ['usd'])->first();

        return $this->success('Deposit options.', [
            'usd_asset'  => $usd ? new AssetResource($usd) : null,
            'stripe_publishable_key' => config('services.stripe.publishable'),
            'pending'    => TransactionResource::collection($pending),
        ]);
    }

    /**
     * Withdrawal preflight — assets + methods + pending withdrawals.
     */
    public function withdrawalOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();

        $pending = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'withdrawal')
            ->where('status', 'pending')
            ->with([
                'asset',
                'subMethod',
            ])
            ->latest()
            ->get();

        $history = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'withdrawal')
            ->whereIn('status', [
                'completed',
                'cancelled',
            ])
            ->with([
                'asset',
                'subMethod',
            ])
            ->latest()
            ->take(20)
            ->get();

        return $this->success('Withdrawal options.', [
            'assets'  => AssetResource::collection(Asset::active()->get()),
            'methods' => MethodResource::collection(
                Method::crypto()->with('subMethods')->get()
            ),
            'pending' => TransactionResource::collection($pending),
            'history' => TransactionResource::collection($history),
        ]);
    }

    /**
     * Transfer preflight — assets + pending transfers.
     */
    public function transferOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();

        $pending = Transaction::where('wallet_id', $wallet->id)
                              ->where('type', 'transfer')
                              ->where('status', 'pending')
                              ->with([
                                  'asset',
                                  'subMethod',
                              ])
                              ->latest()
                              ->get();

        return $this->success('Transfer options.', [
            'assets'  => AssetResource::collection(Asset::active()->get()),
            'pending' => TransactionResource::collection($pending),
        ]);
    }
}
