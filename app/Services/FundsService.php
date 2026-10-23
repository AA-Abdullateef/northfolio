<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Events\DepositInitiated;
use App\Events\TransferInitiated;
use App\Events\WithdrawalInitiated;
use App\Models\AuditLog;
use App\Models\Asset;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FundsService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly StripePaymentService $stripe,
        private readonly TransactionLifecycleService $lifecycle,
    ) {}

    /**
     * Initiate a USD deposit through a Stripe (test-mode) PaymentIntent.
     *
     * The deposit stays Pending. It is completed ONLY by the signature-verified
     * Stripe webhook (payment_intent.succeeded) — never by the client or an admin.
     *
     * @return array{transaction: Transaction, client_secret: ?string}
     */
    public function initiateDeposit(Wallet $wallet, array $data): array
    {
        if ($this->ledger->hasActiveTransaction($wallet->id, TransactionType::Deposit->value)) {
            throw ValidationException::withMessages([
                'deposit' => ['You already have a pending deposit.'],
            ]);
        }

        $usd = Asset::whereRaw('lower(name) = ?', ['usd'])->first();

        if (! $usd) {
            throw ValidationException::withMessages([
                'deposit' => ['USD is not configured on this platform.'],
            ]);
        }

        $usdAmount = $this->assertPrecision($usd, $data['usd_amount'], 'usd_amount');

        return DB::transaction(function () use ($wallet, $usd, $usdAmount) {
            $transaction = Transaction::create([
                'wallet_id' => $wallet->id,
                'type'      => TransactionType::Deposit->value,
                'asset_id'  => $usd->id,
                'amount'    => $usdAmount,
                'status'    => TransactionStatus::Pending->value,
                'reference' => Str::uuid()->toString(),
                'meta'      => ['source' => 'stripe'],
            ]);

            $intent = $this->stripe->createPaymentIntent($usdAmount, [
                'transaction_id' => $transaction->id,
                'wallet_id'      => $wallet->id,
            ]);

            $transaction->update([
                'external_id' => $intent['id'],
                'meta'        => array_merge($transaction->meta ?? [], ['stripe_payment_intent_id' => $intent['id']]),
            ]);

            DepositInitiated::dispatch($wallet, $transaction);

            AuditLog::record(
                action: 'deposit.initiated',
                actorId: $wallet->id,
                actorType: 'wallet',
                subjectType: 'transaction',
                subjectId: $transaction->id,
                after: $transaction->toArray(),
            );

            return [
                'transaction'   => $transaction->load('asset'),
                'client_secret' => $intent['client_secret'] ?? null,
            ];
        });
    }

    /**
     * Preflight validation before creating a withdrawal.
     *
     * @throws ValidationException
     */
    public function withdrawalPreflight(Wallet $wallet, array $data): void
    {
        if ($this->ledger->hasActiveTransaction($wallet->id, TransactionType::Withdrawal->value)) {
            throw ValidationException::withMessages([
                'withdrawal' => ['You already have a pending withdrawal.'],
            ]);
        }

        if (! $this->ledger->hasSufficientBalance($wallet->id, $data['asset_id'], $this->amountFor($data['asset_id'], $data['amount']))) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.'],
            ]);
        }
    }

    public function createWithdrawal(Wallet $wallet, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $data) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['Wallet not found.'],
                ]);
            }

            if ($this->ledger->hasActiveTransaction($locked->id, TransactionType::Withdrawal->value)) {
                throw ValidationException::withMessages([
                    'withdrawal' => ['You already have a pending withdrawal.'],
                ]);
            }

            if (! $this->ledger->hasSufficientBalance($locked->id, $data['asset_id'], $this->amountFor($data['asset_id'], $data['amount']))) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance.'],
                ]);
            }

            $transaction = Transaction::create([
                'wallet_id' => $locked->id,
                'type' => TransactionType::Withdrawal->value,
                'asset_id' => $data['asset_id'],
                'amount' => $this->amountFor($data['asset_id'], $data['amount']),
                'sub_method_id' => $data['sub_method_id'],
                'status' => TransactionStatus::Pending->value,
                'reference' => $data['reference'],
                'meta' => ['sub_method_id' => $data['sub_method_id'] ?? null],
            ]);

            WithdrawalInitiated::dispatch($locked, $transaction);

            AuditLog::record(
                action: 'withdrawal.initiated',
                actorId: $locked->id,
                actorType: 'wallet',
                subjectType: 'transaction',
                subjectId: $transaction->id,
                after: $transaction->toArray(),
            );

            return $transaction->load([
                'asset',
                'subMethod',
            ]);
        });
    }

    /**
     * Preflight validation for an asset transfer.
     *
     * @throws ValidationException
     */
    public function transferPreflight(Wallet $wallet, array $data): void
    {
        if (($data['recipient_id'] ?? null) === $wallet->id) {
            throw ValidationException::withMessages([
                'recipient_id' => ['You cannot transfer to your own wallet.'],
            ]);
        }

        if (! $this->ledger->hasSufficientBalance($wallet->id, $data['asset_id'], $this->amountFor($data['asset_id'], $data['amount']))) {
            throw ValidationException::withMessages([
                'amount' => ['Insufficient balance.'],
            ]);
        }
    }

    public function createTransfer(Wallet $wallet, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $data) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'transfer' => ['Wallet not found.'],
                ]);
            }

            if (($data['recipient_id'] ?? null) === $locked->id) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['You cannot transfer to your own wallet.'],
                ]);
            }

            if (! $this->ledger->hasSufficientBalance($locked->id, $data['asset_id'], $this->amountFor($data['asset_id'], $data['amount']))) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance.'],
                ]);
            }

            $recipient = Wallet::find($data['recipient_id'] ?? null);

            if (! $recipient) {
                throw ValidationException::withMessages([
                    'recipient_id' => ['Recipient wallet no longer exists.'],
                ]);
            }

            $transferId = (string) Str::uuid();

            $outgoing = Transaction::create([
                'wallet_id' => $locked->id,
                'type' => TransactionType::Transfer->value,
                'asset_id' => $data['asset_id'],
                'amount' => $this->amountFor($data['asset_id'], $data['amount']),
                'status' => TransactionStatus::Pending->value,
                'reference' => $recipient->id,
                'meta' => [
                    'direction' => 'outgoing',
                    'transfer_id' => $transferId,
                    'recipient_wallet_id' => $recipient->id,
                ],
            ]);

            Transaction::create([
                'wallet_id' => $recipient->id,
                'type' => TransactionType::Transfer->value,
                'asset_id' => $data['asset_id'],
                'amount' => $this->amountFor($data['asset_id'], $data['amount']),
                'status' => TransactionStatus::Pending->value,
                'reference' => $locked->id,
                'meta' => [
                    'direction' => 'incoming',
                    'transfer_id' => $transferId,
                    'from_wallet_id' => $locked->id,
                ],
            ]);

            TransferInitiated::dispatch($locked, $outgoing);

            AuditLog::record(
                action: 'transfer.initiated',
                actorId: $locked->id,
                actorType: 'wallet',
                subjectType: 'transaction',
                subjectId: $outgoing->id,
                after: $outgoing->toArray(),
            );

            return $outgoing->load('asset');
        });
    }

    // ─── Swap ─────────────────────────────────────────────────────────────────

    /**
     * Initiate a swap: source asset → target asset.
     *
     * swap_charge_rate % is charged on top of the swap amount, not deducted
     * from it — the full amount converts, and the charge is billed as its
     * own separate transaction. Rate is derived from the assets'
     * current_price in USD.
     *
     * Three pending transactions are created, all sharing one swap_id:
     *   - outgoing leg: swap amount leaves the source asset
     *   - incoming leg: converted amount arrives in the target asset
     *   - charge leg:   swap_charge_rate fee, recorded as a withdrawal on
     *                   the source asset so the ledger debits it correctly
     * All three stay Pending until an admin approves the swap. Completing
     * or cancelling any one of them cascades to the rest via
     * Transaction::relatedPendingSwapLegs() — so cancelling a swap also
     * refunds the charge.
     *
     * Example: 2000 USDT → ETH, swap_charge_rate = 1%, USDT=$1, ETH=$3000
     *   charge_amount  = 2000 × 1%   = 20 USDT    (separate charge leg)
     *   total_required = 2000 + 20   = 2020 USDT  (checked against balance)
     *   to_amount      = 2000 / 3000 = 0.6667 ETH (full amount converts, not net-of-charge)
     */
    public function initiateSwap(Wallet $wallet, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $data) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'swap' => ['Wallet not found.'],
                ]);
            }

            if ($this->ledger->hasActiveTransaction($locked->id, TransactionType::Swap->value)) {
                throw ValidationException::withMessages([
                    'swap' => ['You already have a pending swap.'],
                ]);
            }

            $fromAsset = Asset::findOrFail($data['from_asset_id']);
            $toAsset   = Asset::findOrFail($data['to_asset_id']);
            $amount    = $this->assertPrecision($fromAsset, $data['amount'], 'amount');

            if ($fromAsset->current_price === null || ! Money::isPositive($fromAsset->current_price)) {
                throw ValidationException::withMessages([
                    'from_asset_id' => ['Source asset does not have a valid price set.'],
                ]);
            }

            if ($toAsset->current_price === null || ! Money::isPositive($toAsset->current_price)) {
                throw ValidationException::withMessages([
                    'to_asset_id' => ['Target asset does not have a valid price set.'],
                ]);
            }

            $fromPriceUsd = Money::of($fromAsset->current_price);
            $toPriceUsd   = Money::of($toAsset->current_price);
            $chargeRate   = Money::of($fromAsset->swap_charge_rate ?? 0);
            // Fee is charged in the source asset, rounded to that asset's precision.
            $chargeAmount = Money::percent($amount, $chargeRate, (int) ($fromAsset->decimals ?? 8));
            $totalRequired = Money::add($amount, $chargeAmount);

            // Balance must cover the swap amount plus the charge amount
            // (2000 + 20 = 2020 in the example above).
            if (! $this->ledger->hasSufficientBalance($locked->id, $fromAsset->id, $totalRequired)) {
                throw ValidationException::withMessages([
                    'amount' => [
                        "Insufficient balance. You need {$totalRequired} {$fromAsset->name} "
                        . "({$amount} to swap + {$chargeAmount} charge)."
                    ],
                ]);
            }

            // Full amount converts (2000 → 0.6667 ETH above) — not
            // net-of-charge. The charge is billed separately as its own
            // pending leg below, not deducted here.
            // Converted at USD prices, rounded to the TARGET asset's precision.
            $toAmount = Money::roundTo(Money::div(Money::mul($amount, $fromPriceUsd), $toPriceUsd), (int) ($toAsset->decimals ?? 8));

            if (! Money::isPositive($toAmount)) {
                throw ValidationException::withMessages([
                    'amount' => ['Swap amount is too small to convert at the current price.'],
                ]);
            }

            $swapId = (string) Str::uuid();

            // Record 1 — Outgoing: swap amount (2000 USDT above) leaves the
            // source asset. Pending until an admin approves.
            $outgoing = Transaction::create([
                'wallet_id' => $locked->id,
                'type'      => TransactionType::Swap->value,
                'asset_id'  => $fromAsset->id,
                'amount'    => $amount,
                'status'    => TransactionStatus::Pending->value,
                'reference' => $swapId,
                'meta'      => [
                    'direction'      => 'outgoing',
                    'swap_id'        => $swapId,
                    'to_asset_id'    => $toAsset->id,
                    'to_asset_label' => $toAsset->label,
                    'charge_rate'    => $chargeRate,
                    'charge_amount'  => $chargeAmount,
                    'rate_from_usd'  => $fromPriceUsd,
                    'rate_to_usd'    => $toPriceUsd,
                    'to_amount'      => $toAmount,
                ],
            ]);

            // Record 2 — Incoming: converted amount (0.6667 ETH above)
            // arrives in the target asset. Pending until an admin approves.
            Transaction::create([
                'wallet_id' => $locked->id,
                'type'      => TransactionType::Swap->value,
                'asset_id'  => $toAsset->id,
                'amount'    => $toAmount,
                'status'    => TransactionStatus::Pending->value,
                'reference' => $swapId,
                'meta'      => [
                    'direction'        => 'incoming',
                    'swap_id'          => $swapId,
                    'from_asset_id'    => $fromAsset->id,
                    'from_asset_label' => $fromAsset->label,
                ],
            ]);

            // Record 3 — Charge: swap_charge_rate fee (20 USDT above),
            // recorded as a withdrawal on the source asset so the ledger
            // debits it while pending. Stays Pending like the other two
            // legs — not completed until an admin approves — so cancelling
            // the swap also cancels (refunds) this charge via
            // Transaction::relatedPendingSwapLegs(). Together with the
            // outgoing leg, the total debited from the user is
            // amount + chargeAmount (2000 + 20 = 2020 above).
            if (Money::isPositive($chargeAmount)) {
                Transaction::create([
                    'wallet_id' => $locked->id,
                    'type'      => TransactionType::Withdrawal->value,
                    'asset_id'  => $fromAsset->id,
                    'amount'    => $chargeAmount,
                    'status'    => TransactionStatus::Pending->value,
                    'reference' => $swapId,
                    'meta'      => [
                        'swap_charge' => true,
                        'swap_id'     => $swapId,
                        'charge_rate' => $chargeRate,
                    ],
                ]);
            }

            AuditLog::record(
                action:      'swap.initiated',
                actorId:     $locked->id,
                actorType:   'wallet',
                subjectType: 'transaction',
                subjectId:   $outgoing->id,
                after:       $outgoing->toArray(),
            );

            return $outgoing->load('asset');
        });
    }

    // ─── Buy ──────────────────────────────────────────────────────────────────

    /**
     * Buy an asset using the wallet's USD balance (swap-style, instant).
     *
     * Three legs share one swap_id and are completed atomically:
     *   - outgoing USD leg   (usd_amount leaves the USD balance)
     *   - incoming asset leg ((usd_amount - charge) / price)
     *   - charge leg         (buy_charge_rate % of usd_amount, as a USD withdrawal)
     *
     * Example: $50 → BTC at $60,000 with a 2% charge
     *   charge = $1.00, asset received = (50 - 1) / 60000 = 0.00081667 BTC
     *   total debited from USD = $50 (the charge is carved out of the $50).
     *
     * The ledger therefore debits USD for every unit of asset credited.
     */
    public function initiateBuy(Wallet $wallet, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $data) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages(['buy' => ['Wallet not found.']]);
            }

            $asset     = Asset::findOrFail($data['asset_id']);
            $usd       = Asset::whereRaw('lower(name) = ?', ['usd'])->first();
            if (! $usd) {
                throw ValidationException::withMessages(['buy' => ['USD is not configured on this platform.']]);
            }

            if ($asset->id === $usd->id) {
                throw ValidationException::withMessages(['asset_id' => ['Choose a non-USD asset to buy.']]);
            }

            $usdAmount = $this->assertPrecision($usd, $data['usd_amount'], 'usd_amount');

            if ($asset->current_price === null || ! Money::isPositive($asset->current_price)) {
                throw ValidationException::withMessages([
                    'asset_id' => ['This asset does not have a valid price set.'],
                ]);
            }

            $priceUsd    = Money::of($asset->current_price);
            $chargeRate  = Money::of($asset->buy_charge_rate ?? 0);
            $chargeUsd   = Money::percent($usdAmount, $chargeRate, 2);          // fee in cents
            $netUsd      = Money::sub($usdAmount, $chargeUsd);
            $assetAmount = Money::roundTo(Money::div($netUsd, $priceUsd), (int) ($asset->decimals ?? 8));

            if (! Money::isPositive($assetAmount)) {
                throw ValidationException::withMessages([
                    'usd_amount' => ['Amount is too small to buy any of this asset at the current price.'],
                ]);
            }

            if (! $this->ledger->hasSufficientBalance($locked->id, $usd->id, $usdAmount)) {
                throw ValidationException::withMessages(['usd_amount' => ['Insufficient USD balance.']]);
            }

            $buyId = (string) Str::uuid();

            $outgoing = Transaction::create([
                'wallet_id' => $locked->id,
                'type'      => TransactionType::Swap->value,
                'asset_id'  => $usd->id,
                'amount'    => $netUsd,
                'status'    => TransactionStatus::Pending->value,
                'reference' => $buyId,
                'meta'      => [
                    'kind'        => 'buy',
                    'direction'   => 'outgoing',
                    'swap_id'     => $buyId,
                    'to_asset_id' => $asset->id,
                    'usd_amount'  => $usdAmount,
                    'charge_rate' => $chargeRate,
                    'charge_usd'  => $chargeUsd,
                    'price_usd'   => $priceUsd,
                    'to_amount'   => $assetAmount,
                ],
            ]);

            $incoming = Transaction::create([
                'wallet_id' => $locked->id,
                'type'      => TransactionType::Swap->value,
                'asset_id'  => $asset->id,
                'amount'    => $assetAmount,
                'status'    => TransactionStatus::Pending->value,
                'reference' => $buyId,
                'meta'      => [
                    'kind'          => 'buy',
                    'direction'     => 'incoming',
                    'swap_id'       => $buyId,
                    'from_asset_id' => $usd->id,
                    'price_usd'     => $priceUsd,
                    'usd_amount'    => $usdAmount,
                    'charge_usd'    => $chargeUsd,
                ],
            ]);

            if (Money::isPositive($chargeUsd)) {
                Transaction::create([
                    'wallet_id' => $locked->id,
                    'type'      => TransactionType::Withdrawal->value,
                    'asset_id'  => $usd->id,
                    'amount'    => $chargeUsd,
                    'status'    => TransactionStatus::Pending->value,
                    'reference' => $buyId,
                    'meta'      => [
                        'swap_charge' => true,
                        'swap_id'     => $buyId,
                        'charge_rate' => $chargeRate,
                    ],
                ]);
            }

            AuditLog::record(
                action:      'buy.completed',
                actorId:     $locked->id,
                actorType:   'wallet',
                subjectType: 'transaction',
                subjectId:   $outgoing->id,
                after:       $outgoing->toArray(),
            );

            $this->lifecycle->complete($outgoing, TransactionLifecycleService::ACTOR_SYSTEM, $locked->id, notify: false);

            return $incoming->fresh(['asset', 'subMethod']);
        });
    }

    // ─── Sell ─────────────────────────────────────────────────────────────────

    /**
     * Initiate a sell (crypto → fiat).
     *
     * The user sells `amount` of the asset at current_price.
     * sell_charge_rate % is deducted from the gross fiat payout.
     *
     * Example: sell 0.5 BTC, BTC=$60,000, sell_charge=2%
     *   gross_fiat   = 0.5 × 60000 = $30,000
     *   charge_fiat  = 30000 × 2%  = $600
     *   net_fiat     = $29,400 → sent to user's bank
     *
     * The `amount` stored is the crypto being sold (debited from balance).
     */
    public function initiateSell(Wallet $wallet, array $data): Transaction
    {
        return DB::transaction(function () use ($wallet, $data) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if (! $locked) {
                throw ValidationException::withMessages([
                    'sell' => ['Wallet not found.'],
                ]);
            }

            $asset  = Asset::findOrFail($data['asset_id']);
            $amount = $this->assertPrecision($asset, $data['amount'], 'amount');

            if ($this->ledger->hasActiveTransaction($locked->id, TransactionType::Sell->value)) {
                throw ValidationException::withMessages([
                    'sell' => ['You already have a pending sell order.'],
                ]);
            }

            if (! $this->ledger->hasSufficientBalance($locked->id, $asset->id, $amount)) {
                throw ValidationException::withMessages([
                    'amount' => ['Insufficient balance.'],
                ]);
            }

            if ($asset->current_price === null || ! Money::isPositive($asset->current_price)) {
                throw ValidationException::withMessages([
                    'asset_id' => ['This asset does not have a valid price set.'],
                ]);
            }

            $priceUsd   = Money::of($asset->current_price);
            $grossFiat  = Money::roundTo(Money::mul($amount, $priceUsd), 2);
            $chargeRate = Money::of($asset->sell_charge_rate ?? 0);
            $chargeFiat = Money::percent($grossFiat, $chargeRate, 2);
            $netFiat    = Money::sub($grossFiat, $chargeFiat);

            $transaction = Transaction::create([
                'wallet_id'     => $locked->id,
                'type'          => TransactionType::Sell->value,
                'asset_id'      => $asset->id,
                'amount'        => $amount,
                'sub_method_id' => $data['sub_method_id'] ?? null,
                'status'        => TransactionStatus::Pending->value,
                'reference'     => $data['reference'] ?? Str::uuid()->toString(),
                'meta'          => [
                    'gross_fiat'    => $grossFiat,
                    'charge_rate'   => $chargeRate,
                    'charge_fiat'   => $chargeFiat,
                    'net_fiat'      => $netFiat,
                    'price_usd'     => $priceUsd,
                    'sub_method_id' => $data['sub_method_id'] ?? null,
                ],
            ]);

            AuditLog::record(
                action:      'sell.initiated',
                actorId:     $locked->id,
                actorType:   'wallet',
                subjectType: 'transaction',
                subjectId:   $transaction->id,
                after:       $transaction->toArray(),
            );

            return $transaction->load(['asset', 'subMethod']);
        });
    }

    // ─── Amount helpers ───────────────────────────────────────────────────────

    /** Validate a user-supplied amount against an asset's precision; return canonical 8dp string. */
    private function assertPrecision(Asset $asset, mixed $amount, string $field): string
    {
        $raw = is_float($amount) ? sprintf('%.12F', $amount) : trim((string) $amount);

        if (! preg_match('/^\d+(\.\d+)?$/', $raw)) {
            throw ValidationException::withMessages([$field => ['Amount must be a positive decimal number.']]);
        }

        $decimals = (int) ($asset->decimals ?? 8);

        if (! Money::fits($raw, $decimals)) {
            throw ValidationException::withMessages([
                $field => ["{$asset->label} supports at most {$decimals} decimal places."],
            ]);
        }

        if (! Money::isPositive($raw)) {
            throw ValidationException::withMessages([$field => ['Amount must be greater than zero.']]);
        }

        return Money::of($raw);
    }

    private function amountFor(string $assetId, mixed $amount, string $field = 'amount'): string
    {
        return $this->assertPrecision(Asset::findOrFail($assetId), $amount, $field);
    }
}
