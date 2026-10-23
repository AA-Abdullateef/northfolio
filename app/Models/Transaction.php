<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasUuid;

    protected $fillable = [
        'wallet_id',
        'amount',
        'type',
        'asset_id',
        'sub_method_id',
        'meta',
        'status',
        'reference',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:8',
            'meta'   => 'array',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function subMethod(): BelongsTo
    {
        return $this->belongsTo(SubMethod::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForUser($query, string $userId)
    {
        return $query->whereHas('wallet', fn ($walletQuery) =>
            $walletQuery->where('user_id', $userId)
        );
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWithStatus($query, string|array $status)
    {
        return is_array($status)
            ? $query->whereIn('status', $status)
            : $query->where('status', $status);
    }

    public function scopeForAsset($query, string $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::Pending->value;
    }

    public function isCompleted(): bool
    {
        return $this->status === TransactionStatus::Completed->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === TransactionStatus::Cancelled->value;
    }

    public function isRejected(): bool
    {
        return $this->status === TransactionStatus::Rejected->value;
    }

    // ─── Transfer leg resolution ──────────────────────────────────────────────

    public function relatedPendingTransferLeg(): ?self
    {
        if ($this->type !== TransactionType::Transfer->value) {
            return null;
        }

        $direction = $this->meta['direction'] ?? null;
        $oppositeDirection = match ($direction) {
            'outgoing' => 'incoming',
            'incoming' => 'outgoing',
            default => null,
        };

        if ($oppositeDirection === null) {
            return null;
        }

        $transferId = $this->meta['transfer_id'] ?? null;

        if ($transferId) {
            return self::query()
                ->whereKeyNot($this->id)
                ->where('type', TransactionType::Transfer->value)
                ->where('status', TransactionStatus::Pending->value)
                ->where('asset_id', $this->asset_id)
                ->where('amount', $this->amount)
                ->where('meta->direction', $oppositeDirection)
                ->where('meta->transfer_id', $transferId)
                ->first();
        }

        return $this->legacyPendingTransferLeg($direction);
    }

    private function legacyPendingTransferLeg(string $direction): ?self
    {
        if ($direction === 'outgoing') {
            return self::query()
                ->where('type', TransactionType::Transfer->value)
                ->where('status', TransactionStatus::Pending->value)
                ->where('wallet_id', $this->reference)
                ->where('asset_id', $this->asset_id)
                ->where('amount', $this->amount)
                ->where('reference', $this->wallet_id)
                ->where('meta->direction', 'incoming')
                ->where('meta->from_wallet_id', $this->wallet_id)
                ->latest()
                ->first();
        }

        if ($direction === 'incoming') {
            $senderWalletId = $this->meta['from_wallet_id'] ?? $this->reference;

            return self::query()
                ->where('type', TransactionType::Transfer->value)
                ->where('status', TransactionStatus::Pending->value)
                ->where('wallet_id', $senderWalletId)
                ->where('asset_id', $this->asset_id)
                ->where('amount', $this->amount)
                ->where('reference', $this->wallet_id)
                ->where('meta->direction', 'outgoing')
                ->latest()
                ->first();
        }

        return null;
    }

    // ─── Swap leg resolution ──────────────────────────────────────────────────

    /**
     * For any pending transaction belonging to a swap (either of the two swap
     * legs, or the fee leg), find every OTHER pending transaction sharing the
     * same swap_id — regardless of type/direction — so completing or
     * cancelling one leg can cascade to all of them.
     *
     * The fee leg is recorded as type=Withdrawal (so the ledger naturally
     * debits it) rather than type=Swap, which is why this can't filter on
     * `type` the way relatedPendingTransferLeg does — swap_id in meta is the
     * only thing all three rows share.
     */
    public function relatedPendingSwapLegs(): \Illuminate\Support\Collection
    {
        $swapId = $this->meta['swap_id'] ?? null;

        if (! $swapId) {
            return collect();
        }

        return self::query()
            ->whereKeyNot($this->id)
            ->where('status', TransactionStatus::Pending->value)
            ->where('meta->swap_id', $swapId)
            ->get();
    }

    // ─── Labels ───────────────────────────────────────────────────────────────

    public function statusLabel(): string
    {
        return TransactionStatus::from($this->status)->label();
    }

    public function typeLabel(): string
    {
        if (($this->meta['kind'] ?? null) === 'buy' && $this->type === TransactionType::Swap->value) {
            return 'Buy';
        }

        return TransactionType::from($this->type)->label();
    }

    /**
     * SQL expression that reads `amount` as TEXT so PHP never sees a float:
     * MySQL/Postgres return DECIMAL exactly; SQLite (tests/dev only) stores
     * decimals as doubles, so it is formatted by SQLite's own printf at 8 dp (never via a PHP float; limited to ~15 significant digits).
     */
    public static function exactAmountExpression(): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        return match ($driver) {
            'mysql', 'mariadb' => 'CAST(amount AS CHAR)',
            default            => "printf('%.8f', amount)",
        };
    }
}
