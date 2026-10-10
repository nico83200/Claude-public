<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des opérations sensibles (partages, administration, facturation, exports).
 */
class Audit
{
    public static function log(string $action, ?Model $subject = null, array $properties = [], ?int $organizationId = null): AuditLog
    {
        $request = request();
        $user = $request?->user();

        return AuditLog::create([
            'user_id' => $user?->id,
            'organization_id' => $organizationId ?? ($subject?->organization_id ?? null),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 250) ?: null,
        ]);
    }
}
