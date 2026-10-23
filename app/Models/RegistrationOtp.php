<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistrationOtp extends Model
{
    use HasUuid;

    protected $fillable = ['user_id', 'otp', 'attempts', 'used', 'expires_at'];

    protected $hidden = ['otp'];

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

    public function isUsed(): bool
    {
        return $this->used;
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