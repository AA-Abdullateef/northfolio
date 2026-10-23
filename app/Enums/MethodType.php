<?php

namespace App\Enums;

enum MethodType: string
{
    case Crypto = 'crypto';
    case Fiat   = 'fiat';

    public function label(): string
    {
        return match($this) {
            self::Crypto => 'Cryptocurrency',
            self::Fiat   => 'Fiat / Bank Transfer',
        };
    }

    /**
     * Keywords matched case-insensitively against a Method's `name` column.
     * There's no dedicated type column — the seeder bakes the category into
     * the method name itself, so this is where that convention is decoded.
     *
     * 'cryto' covers the seeded typo ("Crytocurrency") so existing seeded
     * data keeps matching even if the seeder spelling is ever corrected.
     */
    public function keywords(): array
    {
        return match($this) {
            self::Crypto => ['crypto', 'cryto', 'coin'],
            self::Fiat   => ['bank', 'fiat', 'transfer'],
        };
    }
}
