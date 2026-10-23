<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending   = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rejected  = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending   => 'Pending',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rejected  => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending   => 'badge-pending',
            self::Completed => 'badge-completed',
            self::Cancelled => 'badge-cancelled',
            self::Rejected  => 'badge-cancelled',
        };
    }

    /**
     * Statuses that count as "active" — block a second pending transaction of the same type.
     */
    public static function activeStatuses(): array
    {
        return [self::Pending->value];
    }

    /**
     * Statuses that lock funds out on the debit side for withdrawals.
     * Pending and completed withdrawals both reduce available balance.
     */
    public static function withdrawalDebitStatuses(): array
    {
        return [self::Pending->value, self::Completed->value];
    }

    /**
     * Statuses that lock funds out on the debit side for outgoing transfers.
     * Same lifecycle as withdrawals: once money has left the wallet (pending),
     * it stays debited through completion.
     */
    public static function transferDebitStatuses(): array
    {
        return [self::Pending->value, self::Completed->value];
    }

    /**
     * Statuses that lock funds on the debit side for swap outgoing legs and sells.
     * Matches the withdrawal/transfer pattern — pending locks, completed debits.
     */
    public static function swapDebitStatuses(): array
    {
        return [self::Pending->value, self::Completed->value];
    }

    /**
     * Statuses that lock funds on the debit side for sell transactions.
     */
    public static function sellDebitStatuses(): array
    {
        return [self::Pending->value, self::Completed->value];
    }
}
