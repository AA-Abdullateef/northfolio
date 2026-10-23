<?php

namespace App\Services;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    public function record(
        string $action,
        ?string $actorId = null,
        ?string $actorType = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?array $before = null,
        ?array $after = null,
    ): AuditLog
    {
        return AuditLog::record(
            action: $action,
            actorId: $actorId,
            actorType: $actorType,
            subjectType: $subjectType,
            subjectId: $subjectId,
            before: $before,
            after: $after
        );
    }
}