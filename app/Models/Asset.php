<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasUuid;

    protected $fillable = [
        'name',
        'label',
        'icon',
        'active',
        'price_source',
        'price_source_id',
        'current_price',
        'price_updated_at',
        'swap_charge_rate',
        'buy_charge_rate',
        'sell_charge_rate',
        'decimals',
    ];

    protected function casts(): array
    {
        return [
            'active'           => 'boolean',
            'decimals'         => 'integer',
            'current_price'    => 'decimal:8',
            'price_updated_at' => 'datetime',
            'swap_charge_rate' => 'decimal:2',
            'buy_charge_rate'  => 'decimal:2',
            'sell_charge_rate' => 'decimal:2',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
