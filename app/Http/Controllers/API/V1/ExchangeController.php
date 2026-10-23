<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\BuyRequest;
use App\Http\Requests\API\V1\SellRequest;
use App\Http\Requests\API\V1\SwapRequest;
use App\Http\Resources\API\V1\AssetResource;
use App\Http\Resources\API\V1\MethodResource;
use App\Http\Resources\API\V1\TransactionResource;
use App\Models\Asset;
use App\Models\Method;
use App\Models\Transaction;
use App\Services\FundsService;
use App\Services\LedgerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExchangeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly FundsService $fundsService,
        private readonly LedgerService $ledger,
    ) {}

    // ─── Swap ─────────────────────────────────────────────────────────────────

    /**
     * Swap preflight — all active assets with prices and charge rates,
     * plus this wallet's pending and recent completed swaps.
     */
    public function swapOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();
        $assets = Asset::active()->whereNotNull('current_price')->get();

        $pending = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'swap')
            ->where('status', 'pending')
            ->where('meta->direction', 'outgoing') // show one row per swap, not both legs
            ->with('asset')
            ->latest()
            ->get();

        $history = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'swap')
            ->whereIn('status', ['completed', 'cancelled'])
            ->where('meta->direction', 'outgoing')
            ->with('asset')
            ->latest()
            ->take(20)
            ->get();

        return $this->success('Swap options.', [
            'assets'  => AssetResource::collection($assets),
            'pending' => TransactionResource::collection($pending),
            'history' => TransactionResource::collection($history),
        ]);
    }

    /**
     * Initiate a swap.
     */
    public function initiateSwap(SwapRequest $request): JsonResponse
    {
        $wallet = $request->wallet();

        try {
            $transaction = $this->fundsService->initiateSwap($wallet, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->errors()[array_key_first($e->errors())][0] ?? 'Swap failed.', $e->errors(), 422);
        }

        return $this->success('Swap initiated. Pending admin approval.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // ─── Buy ──────────────────────────────────────────────────────────────────

    /**
     * Buy preflight — priced assets, buy_charge_rate, the wallet's USD balance and recent buys.
     */
    public function buyOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();
        $assets = Asset::active()->whereNotNull('current_price')
            ->whereRaw('lower(name) != ?', ['usd'])->get();
        $usd    = Asset::whereRaw('lower(name) = ?', ['usd'])->first();

        $history = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'swap')
            ->where('meta->kind', 'buy')
            ->where('meta->direction', 'incoming')
            ->where('status', 'completed')
            ->with('asset')
            ->latest()
            ->take(20)
            ->get();

        return $this->success('Buy options.', [
            'assets'      => AssetResource::collection($assets),
            'usd_balance' => $usd ? $this->ledger->balanceFor($wallet->id, $usd->id) : \App\Support\Money::zero(),
            'history'     => TransactionResource::collection($history),
        ]);
    }

    /**
     * Initiate a buy order.
     */
    public function initiateBuy(BuyRequest $request): JsonResponse
    {
        $wallet = $request->wallet();

        try {
            $transaction = $this->fundsService->initiateBuy($wallet, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->errors()[array_key_first($e->errors())][0] ?? 'Buy failed.', $e->errors(), 422);
        }

        return $this->success('Buy order completed.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }

    // ─── Sell ─────────────────────────────────────────────────────────────────

    /**
     * Sell preflight — assets with prices, sell_charge_rate, and wallet balances,
     * methods, pending sells.
     */
    public function sellOptions(Request $request): JsonResponse
    {
        $wallet = $request->wallet();
        $assets = Asset::active()->whereNotNull('current_price')->get();

        // Attach the wallet's current balance for each asset so the UI can
        // show how much of each asset is available to sell.
        $assetsWithBalance = $assets->map(fn (Asset $asset) => [
            'asset'   => new AssetResource($asset),
            'balance' => $this->ledger->balanceFor($wallet->id, $asset->id),
        ]);

        $pending = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'sell')
            ->where('status', 'pending')
            ->with(['asset', 'subMethod'])
            ->latest()
            ->get();

        $history = Transaction::where('wallet_id', $wallet->id)
            ->where('type', 'sell')
            ->whereIn('status', ['completed', 'cancelled'])
            ->with(['asset', 'subMethod'])
            ->latest()
            ->take(20)
            ->get();

        $methods = MethodResource::collection(Method::fiat()->with('subMethods')->get());

        return $this->success('Sell options.', [
            'assets'  => $assetsWithBalance,
            'methods' => $methods,
            'pending' => TransactionResource::collection($pending),
            'history' => TransactionResource::collection($history),
        ]);
    }

    /**
     * Initiate a sell order.
     */
    public function initiateSell(SellRequest $request): JsonResponse
    {
        $wallet = $request->wallet();

        try {
            $transaction = $this->fundsService->initiateSell($wallet, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->errors()[array_key_first($e->errors())][0] ?? 'Sell failed.', $e->errors(), 422);
        }

        return $this->success('Sell order placed. Pending admin approval.', [
            'transaction' => new TransactionResource($transaction),
        ]);
    }
}
