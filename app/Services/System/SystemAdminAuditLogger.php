<?php

namespace App\Services\System;

use App\Models\SystemAdminAuditLog;
use Illuminate\Database\Eloquent\Model;

class SystemAdminAuditLogger
{
    public function log(
        ?int $actorUserId,
        string $domain,
        string $action,
        ?Model $auditable,
        ?array $beforeData,
        ?array $afterData,
        ?array $metadata = null,
    ): void {
        SystemAdminAuditLog::query()->create([
            'actor_user_id' => $actorUserId,
            'domain' => $domain,
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'metadata' => $metadata,
        ]);
    }
}
