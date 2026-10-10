<?php

namespace App\Support;

/**
 * Catalogue des permissions.
 *
 * Les permissions "cheval" s'appliquent à un dossier de cheval et peuvent être
 * accordées par rôle d'organisation, par partage direct (demi-pension) ou par
 * rattachement d'une écurie. Les permissions "organisation" ne concernent que
 * l'administration de l'espace.
 */
final class Perm
{
    // --- Cheval -----------------------------------------------------------
    public const HORSE_VIEW = 'horse.view';

    public const HORSE_EDIT = 'horse.edit';

    public const HORSE_MANAGE = 'horse.manage'; // partages, transfert, archivage (propriétaire / gérant)

    public const HEALTH_VIEW = 'health.view';

    public const HEALTH_EDIT = 'health.edit';

    public const TREATMENTS_VIEW = 'treatments.view';

    public const CALENDAR_VIEW = 'calendar.view';

    public const CALENDAR_EDIT = 'calendar.edit';

    public const SESSIONS_VIEW = 'sessions.view';

    public const SESSIONS_CREATE = 'sessions.create';

    public const SESSIONS_EDIT_OWN = 'sessions.edit_own';

    public const SESSIONS_EDIT_ALL = 'sessions.edit_all';

    public const COMMENTS_CREATE = 'comments.create';

    public const DOCUMENTS_VIEW = 'documents.view';

    public const DOCUMENTS_CREATE = 'documents.create';

    public const EXPENSES_VIEW = 'expenses.view';

    public const EXPENSES_CREATE = 'expenses.create';

    public const FEEDING_VIEW = 'feeding.view';

    public const FEEDING_EDIT = 'feeding.edit';

    public const DAILYLOG_CREATE = 'dailylog.create';

    // --- Organisation -----------------------------------------------------
    public const ORG_MANAGE = 'org.manage';

    public const MEMBERS_MANAGE = 'members.manage';

    public const BILLING_MANAGE = 'billing.manage';

    public const HORSES_CREATE = 'horses.create';

    public const PROFESSIONALS_MANAGE = 'professionals.manage';

    public const EXERCISES_MANAGE = 'exercises.manage';

    public const EXPENSES_ORG_VIEW = 'expenses.org_view';

    /** Libellés des permissions cheval (ordre d'affichage). */
    public static function horseLabels(): array
    {
        return [
            self::HORSE_VIEW => 'Consulter la fiche',
            self::HORSE_EDIT => 'Modifier la fiche',
            self::HEALTH_VIEW => 'Consulter les soins',
            self::HEALTH_EDIT => 'Saisir et modifier les soins',
            self::TREATMENTS_VIEW => 'Consulter les traitements',
            self::CALENDAR_VIEW => 'Consulter le calendrier',
            self::CALENDAR_EDIT => 'Gérer le calendrier',
            self::SESSIONS_VIEW => 'Consulter les séances',
            self::SESSIONS_CREATE => 'Créer des séances',
            self::SESSIONS_EDIT_OWN => 'Modifier ses propres séances',
            self::SESSIONS_EDIT_ALL => 'Modifier les séances des autres',
            self::COMMENTS_CREATE => 'Ajouter des commentaires et observations',
            self::DOCUMENTS_VIEW => 'Consulter les documents',
            self::DOCUMENTS_CREATE => 'Ajouter des documents',
            self::EXPENSES_VIEW => 'Consulter les dépenses',
            self::EXPENSES_CREATE => 'Saisir des dépenses',
            self::FEEDING_VIEW => 'Consulter l\'alimentation',
            self::FEEDING_EDIT => 'Modifier l\'alimentation',
            self::DAILYLOG_CREATE => 'Saisir le suivi quotidien',
        ];
    }

    public static function orgLabels(): array
    {
        return [
            self::ORG_MANAGE => 'Administrer l\'espace',
            self::MEMBERS_MANAGE => 'Gérer les membres et rôles',
            self::BILLING_MANAGE => 'Gérer l\'abonnement',
            self::HORSES_CREATE => 'Ajouter des chevaux',
            self::PROFESSIONALS_MANAGE => 'Gérer les intervenants',
            self::EXERCISES_MANAGE => 'Gérer les exercices partagés',
            self::EXPENSES_ORG_VIEW => 'Consulter le budget de l\'espace',
        ];
    }

    public static function horseKeys(): array
    {
        return array_keys(self::horseLabels());
    }

    public static function allHorseKeys(): array
    {
        return [...self::horseKeys(), self::HORSE_MANAGE];
    }

    /** Permissions qui ne modifient rien (autorisées en lecture seule). */
    public static function isRead(string $perm): bool
    {
        return str_ends_with($perm, '.view');
    }

    /** Préréglage "demi-pension". */
    public static function halfLeasePreset(): array
    {
        return [
            self::HORSE_VIEW, self::HEALTH_VIEW, self::TREATMENTS_VIEW, self::CALENDAR_VIEW,
            self::SESSIONS_VIEW, self::SESSIONS_CREATE, self::SESSIONS_EDIT_OWN,
            self::COMMENTS_CREATE, self::FEEDING_VIEW, self::DAILYLOG_CREATE,
        ];
    }

    /** Préréglage par défaut pour une écurie qui héberge un cheval (pension). */
    public static function boardingPreset(): array
    {
        return [
            self::HORSE_VIEW, self::CALENDAR_VIEW, self::CALENDAR_EDIT, self::SESSIONS_VIEW,
            self::FEEDING_VIEW, self::FEEDING_EDIT, self::DAILYLOG_CREATE, self::COMMENTS_CREATE,
            self::TREATMENTS_VIEW,
        ];
    }

    /** Rôles système et leurs permissions. */
    public static function systemRoles(): array
    {
        $horse = self::horseKeys();
        $org = array_keys(self::orgLabels());

        return [
            'owner' => ['name' => 'Propriétaire de l\'espace', 'perms' => [...$horse, ...$org]],
            'manager' => ['name' => 'Gérant', 'perms' => [...$horse, ...array_diff($org, [self::BILLING_MANAGE])]],
            'care_manager' => ['name' => 'Responsable des soins', 'perms' => [
                self::HORSE_VIEW, self::HEALTH_VIEW, self::HEALTH_EDIT, self::TREATMENTS_VIEW,
                self::CALENDAR_VIEW, self::CALENDAR_EDIT, self::SESSIONS_VIEW, self::COMMENTS_CREATE,
                self::DOCUMENTS_VIEW, self::DOCUMENTS_CREATE, self::FEEDING_VIEW, self::FEEDING_EDIT,
                self::DAILYLOG_CREATE, self::PROFESSIONALS_MANAGE,
            ]],
            'staff' => ['name' => 'Salarié', 'perms' => [
                self::HORSE_VIEW, self::CALENDAR_VIEW, self::CALENDAR_EDIT, self::SESSIONS_VIEW,
                self::SESSIONS_CREATE, self::SESSIONS_EDIT_OWN, self::COMMENTS_CREATE, self::TREATMENTS_VIEW,
                self::FEEDING_VIEW, self::FEEDING_EDIT, self::DAILYLOG_CREATE,
            ]],
            'rider' => ['name' => 'Cavalier', 'perms' => [
                self::HORSE_VIEW, self::CALENDAR_VIEW, self::SESSIONS_VIEW, self::SESSIONS_CREATE,
                self::SESSIONS_EDIT_OWN, self::COMMENTS_CREATE, self::DAILYLOG_CREATE,
            ]],
        ];
    }

    /** Rôles qui obtiennent la gestion complète des chevaux de l'espace. */
    public const MANAGING_ROLES = ['owner', 'manager'];
}
