<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal des actions : opérations sensibles écrites ici avec leur détail (partages, administration, facturation,
 * exports), et toutes les autres saisies des utilisateurs notées par RecordUserActions (sans le contenu des formulaires).
 */
class Audit
{
    /** Une ligne a déjà été écrite pour la requête en cours : RecordUserActions n'en ajoute pas une seconde. */
    public static bool $written = false;

    public static function log(string $action, ?Model $subject = null, array $properties = [], ?int $organizationId = null): AuditLog
    {
        $request = request();
        $user = $request?->user();

        self::$written = true;

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
