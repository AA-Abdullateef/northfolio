<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null; // audit logs are immutable — no updated_at

    protected $fillable = [
        'actor_id', 'actor_type', 'action',
        'subject_type', 'subject_id',
        'before', 'after',
        'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after'  => 'array',
        ];
    }

    /**
     * Convenience factory for creating audit entries without
     * needing to pass request context manually each time.
     */
    public static function record(
        string  $action,
        ?string $actorId   = null,
        ?string $actorType = null,
        ?string $subjectType = null,
        ?string $subjectId   = null,
        ?array  $before      = null,
        ?array  $after       = null,
    ): static {
        return static::create([
            'actor_id'     => $actorId,
            'actor_type'   => $actorType,
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'before'       => $before,
            'after'        => $after,
            'ip'           => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}