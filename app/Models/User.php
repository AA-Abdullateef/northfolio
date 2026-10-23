<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuid, Notifiable;

    protected $keyType  = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id',
        'username',
        'email',
        'email_verified_at',
        'profile_completed',
        'country_id',
        'admin',
        'two_factor',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'id'                => 'string',
            'email_verified_at' => 'datetime',
            'profile_completed' => 'boolean',
            'admin'             => 'boolean',
            'two_factor'        => 'boolean',
            'password'          => 'hashed',
        ];
    }

    // ─── Relationships ──────────────────────────────────────────────────────────

    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    public function photo(): HasOne
    {
        return $this->hasOne(ProfilePhoto::class);
    }

    public function transactions(): HasManyThrough
    {
        return $this->hasManyThrough(Transaction::class, Wallet::class, 'user_id', 'wallet_id');
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    public function tokens2fa(): HasMany
    {
        return $this->hasMany(UserToken::class);
    }

    public function registrationOtps(): HasMany
    {
        return $this->hasMany(RegistrationOtp::class);
    }

    public function passwordResetOtps(): HasMany
    {
        return $this->hasMany(PasswordResetOtp::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeCustomers($query)
    {
        return $query->where('admin', false);
    }

    public function scopeAdmins($query)
    {
        return $query->where('admin', true);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    public function isAdmin(): bool
    {
        return (bool) $this->admin;
    }

    public function isVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function fullName(): string
    {
        return trim(
            ($this->profile?->first_name ?? '') . ' ' . ($this->profile?->last_name ?? '')
        ) ?: $this->username;
    }
}
