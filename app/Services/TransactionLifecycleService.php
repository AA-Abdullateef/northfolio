<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InvalidTransactionTransition;
use App\Models\AuditLog;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY place a transaction changes status.
 *
 * Admin actions, user cancellation, the Stripe webhook and instant trades (Buy)
 * all go through complete()/cancel(), so the rules below apply uniformly:
 *
 *  - pending → completed | cancelled is the only legal move; completed and
 *    cancelled are terminal and can never be processed again.
 *  - Linked legs (transfer in/out, swap out/in/fee) move together inside ONE
 *    database transaction, or not at all. If any leg is not pending the whole
 *    operation is refused (no partial or contradictory states).
 *  - Rows are re-read under a lock (SELECT … FOR UPDATE on MySQL/Postgres), so
 *    a stale in-memory model or a concurrent request cannot apply a transition
 *    twice. (SQLite ignores row locks but serialises writers.)
 *  - Cancelling releases reserved funds automatically: balances count pending
 *    debits, so a cancelled leg (including the fee leg) simply stops counting.
 *  - Deposits are provider-backed: only the 'system' actor (verified Stripe
 *    webhook) may complete or cancel them. Users may only cancel (never
 *    complete) their own non-deposit transactions.
 */
class TransactionLifecycleService
{
    public const ACTOR_SYSTEM = 'system';
    public const ACTOR_ADMIN  = 'admin';
    public const ACTOR_USER   = 'user';

    public function __construct(private readonly NotificationService $notifications) {}

    /** @return Collection<int, Transaction> every leg that was completed */
    public function complete(Transaction $transaction, string $actorType, ?string $actorId = null, bool $notify = true): Collection
    {
        return $this->transition($transaction, TransactionStatus::Completed, $actorType, $actorId, $notify);
    }

    /** @return Collection<int, Transaction> every leg that was cancelled */
    public function cancel(Transaction $transaction, string $actorType, ?string $actorId = null, bool $notify = true): Collection
    {
        return $this->transition($transaction, TransactionStatus::Cancelled, $actorType, $actorId, $notify);
    }

    private function transition(
        Transaction $transaction,
        TransactionStatus $to,
        string $actorType,
        ?string $actorId,
        bool $notify,
    ): Collection {
        $legs = DB::transaction(function () use ($transaction, $to, $actorType, $actorId): Collection {
            // Re-read under lock: never trust the caller's (possibly stale) model.
            $target = Transaction::whereKey($transaction->getKey())->lockForUpdate()->first();

            if (! $target) {
                throw new InvalidTransactionTransition('Transaction not found.');
            }

            $this->authorize($target, $to, $actorType);

            if (! $target->isPending()) {
                throw new InvalidTransactionTransition(
                    "Transaction is already {$target->status}; only pending transactions can be {$to->value}."
                );
            }

            $group = $this->lockGroup($target);

            foreach ($group as $leg) {
                if (! $leg->isPending()) {
                    throw new InvalidTransactionTransition(
                        'Linked transaction legs are in inconsistent states; refusing a partial transition.'
                    );
                }
            }

            $this->assertGroupShape($target, $group);

            $before = $group->mapWithKeys(fn (Transaction $l) => [$l->id => $l->status])->all();

            foreach ($group as $leg) {
                $leg->update(['status' => $to->value]);
            }

            AuditLog::record(
                action: 'transaction.' . $to->value,
                actorId: $actorId,
                actorType: $actorType,
                subjectType: 'transaction',
                subjectId: $target->id,
                before: ['legs' => $before],
                after: ['status' => $to->value, 'legs' => $group->pluck('id')->all()],
            );

            return $group->map(fn (Transaction $l) => $l->fresh('wallet.user'));
        });

        if ($notify) {
            foreach ($legs as $leg) {
                if ($leg->wallet) {
                    $this->notifications->sendTransactionNotice($leg->wallet, $leg);
                }
            }
        }

        return $legs;
    }

    private function authorize(Transaction $t, TransactionStatus $to, string $actor): void
    {
        if ($t->type === TransactionType::Deposit->value && $actor !== self::ACTOR_SYSTEM) {
            throw new InvalidTransactionTransition('Deposits are completed or cancelled only by the verified payment provider.');
        }

        if ($actor === self::ACTOR_USER && $to === TransactionStatus::Completed) {
            throw new InvalidTransactionTransition('Users cannot complete transactions.');
        }
    }

    /** Lock the transaction plus every leg that shares its transfer_id / swap_id. */
    private function lockGroup(Transaction $target): Collection
    {
        $swapId     = $target->meta['swap_id'] ?? null;
        $transferId = $target->meta['transfer_id'] ?? null;

        $group = Transaction::query()
            ->where(function ($q) use ($target, $swapId, $transferId) {
                $q->whereKey($target->id);
                if ($swapId) {
                    $q->orWhere('meta->swap_id', $swapId);
                }
                if ($transferId) {
                    $q->orWhere('meta->transfer_id', $transferId);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        // Legacy transfers created before transfer_id existed: pair by heuristic.
        if (! $transferId && $target->type === TransactionType::Transfer->value) {
            $legacy = $target->relatedPendingTransferLeg();
            if ($legacy && ! $group->contains('id', $legacy->id)) {
                $group->push($legacy);
            }
        }

        return $group;
    }

    private function assertGroupShape(Transaction $target, Collection $group): void
    {
        if ($target->type === TransactionType::Transfer->value && $group->count() !== 2) {
            throw new InvalidTransactionTransition('A transfer must have exactly one outgoing and one incoming leg.');
        }

        $isSwap = $target->type === TransactionType::Swap->value || ($target->meta['swap_charge'] ?? false);

        if ($isSwap) {
            $dirs = $group->map(fn (Transaction $l) => $l->meta['direction'] ?? null);

            if (! $dirs->contains('outgoing') || ! $dirs->contains('incoming')) {
                throw new InvalidTransactionTransition('A swap needs both an outgoing and an incoming leg.');
            }
        }
    }
}
