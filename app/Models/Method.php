<?php

namespace App\Models;

use App\Enums\MethodType;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Method extends Model
{
    use HasUuid;

    protected $fillable = ['name'];

    public function subMethods(): HasMany
    {
        return $this->hasMany(SubMethod::class);
    }

    /**
     * Match methods whose `name` contains one of the given type's keywords.
     * This is the single place that decodes the name -> category convention,
     * so changing what counts as "crypto" or "fiat" only happens here.
     */
    public function scopeOfType(Builder $query, MethodType $type): Builder
    {
        return $query->where(function (Builder $q) use ($type) {
            foreach ($type->keywords() as $keyword) {
                $q->orWhere('name', 'like', "%{$keyword}%");
            }
        });
    }

    /**
     * Methods used for deposit & withdrawal (crypto-only).
     */
    public function scopeCrypto(Builder $query): Builder
    {
        return $query->ofType(MethodType::Crypto);
    }

    /**
     * Methods used for buy & sell (bank transfer / fiat-only).
     */
    public function scopeFiat(Builder $query): Builder
    {
        return $query->ofType(MethodType::Fiat);
    }
}