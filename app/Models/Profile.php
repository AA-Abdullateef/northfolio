<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    use HasUuid;

    protected $fillable = [
        'id',
        'user_id',
        'first_name', 'last_name', 'gender',
        'phone', 'state', 'address', 'zip', 'dob',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
