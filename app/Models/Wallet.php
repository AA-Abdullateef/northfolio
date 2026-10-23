<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class Wallet extends Authenticatable
{
    use HasApiTokens, HasUuid;

    protected $fillable = ['id', 'user_id', 'mnemonic_hash', 'fingerprint', 'public_id'];

    protected $hidden = ['mnemonic_hash'];

    protected static function booted(): void
    {
        static::creating(function (Wallet $wallet): void {
            if (! $wallet->getKey()) {
                $wallet->{$wallet->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // A wallet owns transactions
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Resolve the notification email for this wallet.
     * Returns the linked user's email, or null if no user or no email set.
     * Used before sending any transaction notification.
     */
    public function notificationEmail(): ?string
    {
        return $this->user?->email ?? null;
    }

    /**
     * Lookup identifier for a recovery phrase: HMAC-SHA256(APP_KEY, phrase).
     * Lets sign-in find a wallet without storing the phrase. Rotating APP_KEY
     * invalidates fingerprints and public ids (documented limitation).
     */
    public static function fingerprint(string $phrase): string
    {
        return hash_hmac('sha256', self::normalizeMnemonic($phrase), config('app.key'));
    }

    /** Legacy fallback lookup: plain SHA-256 of the normalised phrase (not secret-keyed). */
    public static function mnemonicHash(string $phrase): string
    {
        return hash('sha256', self::normalizeMnemonic($phrase));
    }

    /**
     * Shareable demo identifier used as a transfer address.
     * It is a keyed hash, NOT a cryptographic public key: it cannot verify
     * signatures and no private key exists.
     */
    public static function publicId(string $phrase): string
    {
        return hash_hmac('sha256', 'public:' . self::normalizeMnemonic($phrase), config('app.key'));
    }

    public static function normalizeMnemonic(string $phrase): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $phrase)));
    }
}
