<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetOtp extends Model
{
    use HasUuid;

    protected $fillable = [
        'user_id', 'otp', 'reset_token',
        'attempts', 'used', 'expires_at',
    ];

    protected $hidden = ['otp', 'reset_token'];

    protected function casts(): array
    {
        return [
            'used'       => 'boolean',
            'attempts'   => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasExceededAttempts(int $max = 5): bool
    {
        return $this->attempts >= $max;
    }

    public function scopeActiveForUser($query, string $userId)
    {
        return $query->where('user_id', $userId)
                     ->where('used', false)
                     ->where('expires_at', '>', now());
    }
}