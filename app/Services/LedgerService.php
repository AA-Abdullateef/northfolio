<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Transaction;
use App\Support\Money;

class LedgerService
{
    /**
     * Canonical balance formula for a user's asset.
     *
     * Credits:
     *   + completed deposits
     *   + completed incoming transfers
     *   + completed buys
     *   + completed incoming swaps (receiving leg)
     *
     * Debits:
     *   - pending + completed withdrawals (all non-cancelled)
     *   - pending + completed outgoing transfers
     *   - pending + completed sells
     *   - pending + completed outgoing swaps (source leg, full amount incl. charge)
     */
    public function balanceFor(string $walletId, string $assetId): string
    {
        $rows = Transaction::query()
            ->where('wallet_id', $walletId)
            ->where('asset_id', $assetId)
            ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Completed->value])
            ->toBase()
            ->selectRaw('type, status, meta, ' . Transaction::exactAmountExpression() . ' AS amount')
            ->get();

        $balance = Money::zero();

        foreach ($rows as $row) {
            $amount    = Money::of((string) $row->amount);
            $completed = $row->status === TransactionStatus::Completed->value;
            $direction = json_decode($row->meta ?? 'null', true)['direction'] ?? null;

            $delta = match ($row->type) {
                // Credits count only once completed.
                TransactionType::Deposit->value,
                TransactionType::Buy->value      => $completed ? $amount : null,
                TransactionType::Transfer->value,
                TransactionType::Swap->value     => $direction === 'incoming'
                    ? ($completed ? $amount : null)
                    : ($direction === 'outgoing' ? '-' . $amount : null),
                // Debits count while pending (funds locked) and when completed.
                TransactionType::Withdrawal->value,
                TransactionType::Sell->value     => '-' . $amount,
                default                          => null,
            };

            if ($delta !== null) {
                $balance = Money::add($balance, $delta);
            }
        }

        return $balance;
    }

    /**
     * Balance for every active asset the platform supports.
     * Returns ['asset_uuid' => '12.50000000', ...] (8-dp strings)
     */
    public function allBalancesFor(string $walletId): array
    {
        return Asset::active()
                    ->get()
                    ->mapWithKeys(fn (Asset $asset) => [
                        $asset->id => $this->balanceFor($walletId, $asset->id),
                    ])
                    ->all();
    }

    /**
     * Total portfolio value in USD, converting each asset's balance using
     * its stored current_price. Assets with no price yet (never synced, or
     * price_source is null/manual-without-a-price) are excluded from the
     * total and flagged via unpriced_asset_count so the frontend can show
     * "value unavailable" rather than silently under-counting.
     */
    public function totalUsdBalanceFor(string $walletId): array
    {
        $lines = Asset::active()->get()->map(function (Asset $asset) use ($walletId) {
            $balance = $this->balanceFor($walletId, $asset->id);
            $price   = $asset->current_price !== null ? Money::of($asset->current_price) : null;
            $priced  = $price !== null && Money::isPositive($price);
            // Valuation is display-only: exact product, rounded to cents.
            $value   = $priced ? Money::roundTo(Money::mul($balance, $price), 2) : null;

            return [
                'asset_id'          => $asset->id,
                'balance'           => $balance,
                'price_usd'         => $priced ? $price : null,
                'value_usd'         => $value,
                'price_updated_at'  => $asset->price_updated_at?->toISOString(),
            ];
        });

        return [
            'total_usd'            => $lines->reduce(fn (string $c, array $l) => Money::add($c, $l['value_usd'] ?? Money::zero()), Money::zero()),
            'assets'                => $lines->values()->all(),
            'unpriced_asset_count' => $lines->filter(fn (array $l) => $l['price_usd'] === null)->count(),
        ];
    }

    /**
     * Guard: does the user have enough balance to debit this asset?
     */
    public function hasSufficientBalance(string $walletId, string $assetId, string|int $amount): bool
    {
        return Money::cmp($this->balanceFor($walletId, $assetId), $amount) >= 0;
    }

    /**
     * Guard: does the user already have a pending transaction of this type?
     * Used to block duplicate pending deposits / withdrawals.
     */
    /**
     * Guard: does the user already have a pending transaction of this type?
     * Used to block duplicate pending deposits / withdrawals / buys / sells / swaps.
     *
     * A swap's fee leg is recorded as type=Withdrawal (see FundsService::initiateSwap)
     * so the ledger naturally debits it, and it's now created Pending rather than
     * immediately Completed so it can be refunded if the swap is cancelled. That
     * means it would otherwise look like a real pending withdrawal here and
     * incorrectly block the user from withdrawing while a swap is in flight —
     * excluded via the meta.swap_charge flag it's tagged with.
     */
    public function hasActiveTransaction(string $walletId, string $type): bool
    {
        return Transaction::where('wallet_id', $walletId)
                          ->where('type', $type)
                          ->where('status', TransactionStatus::Pending->value)
                          ->where(function ($query) {
                              $query->whereNull('meta->swap_charge')
                                    ->orWhere('meta->swap_charge', false);
                          })
                          ->exists();
    }

    /**
     * Platform-wide totals for the admin dashboard.
     */
    public function platformTotals(): array
    {
        return Asset::active()
            ->get()
            ->map(fn (Asset $asset) => [
                'asset'       => $asset,
                'deposits'    => $this->platformSum($asset->id, TransactionType::Deposit->value,    [TransactionStatus::Completed->value]),
                'withdrawals' => $this->platformSum($asset->id, TransactionType::Withdrawal->value, TransactionStatus::withdrawalDebitStatuses()),
                'transfers'   => $this->platformSum($asset->id, TransactionType::Transfer->value,   [TransactionStatus::Completed->value], 'outgoing'),
                'swaps_out'   => $this->platformSum($asset->id, TransactionType::Swap->value,       [TransactionStatus::Completed->value], 'outgoing'),
                'swaps_in'    => $this->platformSum($asset->id, TransactionType::Swap->value,       [TransactionStatus::Completed->value], 'incoming'),
                'buys'        => $this->platformSum($asset->id, TransactionType::Buy->value,        [TransactionStatus::Completed->value]),
                'sells'       => $this->platformSum($asset->id, TransactionType::Sell->value,       [TransactionStatus::Completed->value]),
            ])
            ->filter(fn (array $total) =>
                Money::cmp($total['deposits'], 0) !== 0
                || Money::cmp($total['withdrawals'], 0) !== 0
                || Money::cmp($total['transfers'], 0) !== 0
                || Money::cmp($total['swaps_out'], 0) !== 0
                || Money::cmp($total['buys'], 0) !== 0
                || Money::cmp($total['sells'], 0) !== 0
            )
            ->values()
            ->all();
    }

    // ─── Private ─────────────────────────────────────────────────────────────────

    private function platformSum(string $assetId, string $type, array $statuses, ?string $direction = null): string
    {
        $query = Transaction::where('asset_id', $assetId)
            ->where('type', $type)
            ->whereIn('status', $statuses);

        if ($direction !== null) {
            $query->where('meta->direction', $direction);
        }

        return $query->toBase()
            ->selectRaw(Transaction::exactAmountExpression() . ' AS amount')
            ->pluck('amount')
            ->reduce(fn (string $c, $v) => Money::add($c, (string) $v), Money::zero());
    }
}
