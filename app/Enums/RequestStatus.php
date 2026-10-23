<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClass(): string
    {
        return match($this) {
            self::Pending  => 'badge-pending',
            self::Approved => 'badge-completed',
            self::Rejected => 'badge-cancelled',
        };
    }
}