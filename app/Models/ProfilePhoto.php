<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilePhoto extends Model
{
    use HasUuid;

    protected $fillable = ['id', 'user_id', 'img'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return asset('storage/' . $this->img);
    }
    
}
