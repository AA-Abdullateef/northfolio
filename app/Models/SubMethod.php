<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubMethod extends Model
{
    use HasUuid;

    protected $fillable = [
        'method_id',
        'name',
        'account_name',
        'account_number',
        'bank_name',
        'routing_number',
        'swift_code',
        'iban',
        'wallet_address',
        'network',
        'instructions',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}