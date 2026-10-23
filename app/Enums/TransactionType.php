<?php

namespace App\Enums;

enum TransactionType: string
{
    case Deposit    = 'deposit';
    case Withdrawal = 'withdrawal';
    case Transfer   = 'transfer';
    case Swap       = 'swap';
    case Buy        = 'buy';
    case Sell       = 'sell';

    public function label(): string
    {
        return match($this) {
            self::Deposit    => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Transfer   => 'Transfer',
            self::Swap       => 'Swap',
            self::Buy        => 'Buy',
            self::Sell       => 'Sell',
        };
    }
}
