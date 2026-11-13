<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Libellés lisibles du journal (« Séance · ajout ») à partir des codes enregistrés : noms de pages pour les saisies
 * notées automatiquement (sessions.store), codes détaillés pour les autres (care.created). Code inconnu : affiché tel quel.
 */
class AuditLabels
{
    /** Élément concerné, du préfixe le plus précis au plus général. */
    public const SUBJECTS = [
        'horses.observations.updates' => 'Nouvelle d\'un point d\'attention', 'horses.care' => 'Soin', 'horses.treatments' => 'Traitement', 'horses.observations' => 'Point d\'attention',
        'horses.logs' => 'Suivi du jour', 'horses.photos' => 'Photo du cheval', 'horses.documents' => 'Document du cheval',
        'horses.feeding' => 'Alimentation', 'horses.weight' => 'Poids', 'horses.sharing' => 'Partage', 'horses.riding-days' => 'Jours de monte',
        'horses.half-leases' => 'Demi-pension', 'horses.professionals' => 'Intervenant du cheval', 'horses.ownerships' => 'Propriété du cheval',
        'horses.pedigree' => 'Généalogie', 'horses.emergency' => 'Fiche d\'urgence', 'horses.prescription' => 'Ordonnance lue',
        'horses.vet-summary' => 'Résumé vétérinaire', 'horses.assignments' => 'Rattachement à une écurie', 'horses' => 'Cheval',
        'sessions.exercises' => 'Exercice de séance', 'sessions.comments' => 'Commentaire de séance', 'sessions.debrief' => 'Bilan de séance', 'sessions' => 'Séance',
        'calendar' => 'Agenda', 'budget.incomes' => 'Recette', 'budget.recurring-incomes' => 'Recette récurrente', 'budget.recurring' => 'Dépense récurrente',
        'budget.invoice' => 'Facture lue', 'budget' => 'Dépense', 'boarding' => 'Pension', 'half-leases' => 'Demi-pension',
        'professionals.rates' => 'Tarif d\'enseignant', 'professionals' => 'Intervenant', 'lesson-costs' => 'Frais de cours', 'lesson-packages' => 'Carte de cours',
        'organization.members' => 'Membre de l\'espace', 'organization.roles' => 'Rôle', 'organization.invitations' => 'Invitation dans l\'espace',
        'organization' => 'Espace', 'organizations' => 'Espace', 'invitations' => 'Invitation', 'sharing.received' => 'Partage reçu', 'sharing' => 'Partage',
        'assignments' => 'Rattachement à une écurie', 'assistant' => 'Assistant', 'exercises' => 'Exercice', 'templates' => 'Modèle de séance',
        'billing.credits' => 'Crédits assistant', 'billing' => 'Abonnement', 'settings.daily-reminder' => 'Rappel du soir', 'settings.notifications' => 'Notifications',
        'settings.profile' => 'Profil', 'settings.riding-level' => 'Niveau de cavalier', 'settings.data-request' => 'Demande RGPD', 'settings.password' => 'Mot de passe',
        'settings' => 'Paramètres', 'two-factor' => 'Double authentification', 'user-password' => 'Mot de passe', 'user-profile-information' => 'Profil',
        'password' => 'Mot de passe', 'verification' => 'Vérification de l\'e-mail', 'social' => 'Connexion Google / Apple', 'support' => 'Message au support',
        'referral' => 'Parrainage', 'care' => 'Soin', 'observations' => 'Point d\'attention', 'logs' => 'Suivi du jour',
        // Codes détaillés (Audit::log).
        'auth' => 'Connexion', 'grant' => 'Partage', 'half_lease' => 'Demi-pension', 'income' => 'Recette', 'expense' => 'Dépense', 'document' => 'Document',
        'export' => 'Export', 'gdpr' => 'RGPD', 'feeding' => 'Alimentation', 'treatment' => 'Traitement', 'horse' => 'Cheval', 'invitation' => 'Invitation',
        'member' => 'Membre de l\'espace', 'role' => 'Rôle', 'ownership' => 'Propriété du cheval', 'license' => 'Licence', 'assignment' => 'Rattachement à une écurie',
        'assistant_credits' => 'Crédits assistant', 'simulation' => 'Simulation', 'upsell' => 'Offre supérieure', 'user' => 'Compte', 'admin' => 'Administration',
    ];

    /** Ce qui a été fait (dernier mot du code, ou mots du code détaillé). */
    public const VERBS = [
        'store' => 'ajout', 'created' => 'ajout', 'update' => 'modification', 'updated' => 'modification', 'destroy' => 'suppression', 'deleted' => 'suppression',
        'restore' => 'restauration', 'restored' => 'restauration', 'archive' => 'archivage', 'archived' => 'archivage', 'unarchive' => 'désarchivage', 'unarchived' => 'désarchivage',
        'cancel' => 'annulation', 'done' => 'validation', 'resolve' => 'clôture', 'reopen' => 'réouverture', 'accept' => 'acceptation', 'accepted' => 'acceptation',
        'decline' => 'refus', 'respond' => 'réponse', 'accept-all' => 'tout accepter', 'revoke' => 'retrait', 'revoked' => 'retrait', 'resend' => 'renvoi', 'resent' => 'renvoi',
        'invite' => 'envoi', 'end' => 'fin', 'ended' => 'fin', 'pay' => 'paiement', 'paid' => 'payé', 'receive' => 'reçue', 'toggle' => 'pause / reprise',
        'duplicate' => 'copie', 'transfer' => 'transfert', 'transferred' => 'transfert', 'leave' => 'quitté', 'left' => 'quitté', 'pause' => 'suspendu', 'paused' => 'suspendu',
        'resume' => 'repris', 'resumed' => 'repris', 'attach' => 'ajout', 'detach' => 'retrait', 'administer' => 'prise notée', 'request' => 'demande', 'answer' => 'réponse',
        'give' => 'jour donné', 'absence' => 'absence', 'cancel-request' => 'demande retirée', 'read' => 'lecture', 'save' => 'enregistrement', 'send' => 'envoi',
        'sent' => 'envoi', 'generate' => 'génération', 'email' => 'envoi par e-mail', 'emailed' => 'envoi par e-mail', 'enable' => 'activation', 'disable' => 'désactivation',
        'stop' => 'arrêt', 'confirm' => 'confirmation', 'checkout' => 'paiement commencé', 'swap' => 'changement d\'offre', 'redeem' => 'code promo', 'buy' => 'achat',
        'portal' => 'portail de paiement', 'login' => 'connexion', 'login_remembered' => 'connexion (appareil mémorisé)', 'logout' => 'déconnexion',
        'unlink' => 'déliée', 'callback' => 'connexion', 'command' => 'demande', 'speak' => 'lecture à voix haute', 'transcribe' => 'dictée', 'undo' => 'annulée',
        'undone' => 'annulée', 'main' => 'photo principale', 'plan' => 'prévue', 'debrief' => 'bilan', 'cost' => 'frais', 'template' => 'modèle',
        'layout' => 'mise en page', 'move' => 'déplacement', 'quick' => 'depuis la notification', 'reminders' => 'rappels', 'reminder' => 'rappel', 'rhythm' => 'rythme',
        'duration' => 'durée', 'skip-month' => 'mois sans mensualité', 'cancel-notice' => 'préavis annulé', 'use' => 'utilisé', 'until' => 'fin de la série',
        'regenerate' => 'nouveau lien', 'proposed' => 'proposée', 'viewed' => 'consulté', 'suspended' => 'suspendu', 'reactivated' => 'réactivé', 'expired' => 'expirée',
        'downloaded' => 'téléchargé', 'export' => 'export', 'purged' => 'purge', 'started' => 'lancée', 'finished' => 'terminée', 'clicked' => 'clic', 'added' => 'ajout',
        'removed' => 'retrait', 'role_changed' => 'rôle changé', 'email_changed' => 'e-mail changé', 'requested' => 'demande',
        'created_from_prescription' => 'ajout depuis une ordonnance', 'backup_key_viewed' => 'clé de sauvegarde consultée',
    ];

    public static function label(string $action): string
    {
        $subject = null;
        foreach (self::SUBJECTS as $prefix => $name) {
            if ($action === $prefix || str_starts_with($action, $prefix.'.')) {
                $subject = $name;
                $rest = substr($action, strlen($prefix) + 1);
                break;
            }
        }
        if (! $subject) {
            return $action;
        }
        $last = $rest === '' || $rest === false ? null : Str::afterLast($rest, '.');
        $verb = $last === null ? null : (self::VERBS[$last] ?? self::phrase($last));
        if ($subject === 'Connexion' && $verb) {
            return Str::ucfirst($verb);
        }

        return $verb ? $subject.' · '.$verb : $subject;
    }

    /** Mots des codes détaillés (« user_suspended » → « utilisateur suspendu »). */
    private const WORDS = [
        'user' => 'utilisateur', 'organization' => 'espace', 'plan' => 'offre', 'video' => 'vidéo', 'file' => 'fichier', 'license' => 'licence', 'settings' => 'réglages',
        'mail' => 'e-mail', 'design' => 'mise en forme', 'logo' => 'logo', 'test' => 'test', 'backup' => 'sauvegarde', 'key' => 'clé', 'promo' => 'promo', 'code' => 'code',
        'exercise' => 'exercice', 'media' => 'média', 'update' => 'mise à jour', 'announcement' => 'annonce', 'gdpr' => 'RGPD', 'request' => 'demande',
        'stripe' => 'Stripe', 'event' => 'événement', 'super' => 'super', 'admin' => 'admin', 'vaccine' => 'vaccins', 'rules' => 'règles', 'acquisition' => 'acquisition',
        'source' => 'source', 'assistant' => 'assistant', 'social' => 'connexion Google / Apple', 'health' => 'santé', 'share' => 'partage', 'emergency' => 'urgence',
        'sheet' => 'fiche', 'recurring' => 'récurrente', 'series' => 'série', 'contract' => 'contrat', 'statement' => 'relevé', 'statements' => 'relevés',
        'check' => 'contrôle', 'from' => 'depuis', 'prescription' => 'ordonnance', 'reminder' => 'rappel', 'reminders' => 'rappels', 'rhythm' => 'rythme',
        'permissions' => 'droits', 'cancel' => 'résiliation', 'resume' => 'reprise', 'swap' => 'changement', 'price' => 'prix', 'checkout' => 'paiement',
        'account' => 'compte', 'banked' => 'cumulé', 'signed' => 'inscrit', 'up' => '', 'grace' => 'délai de grâce', 'message' => 'message', 'document' => 'document',
        'connected' => 'connectée', 'disconnected' => 'déconnectée', 'uploaded' => 'déposé', 'published' => 'publiée', 'unpublished' => 'retirée',
        'reprocessed' => 'retraité', 'reset' => 'réinitialisées', 'promoted' => 'promu', 'anonymized' => 'anonymisé', 'linked' => 'liée', 'unlinked' => 'déliée',
        'registered' => 'inscription', 'rewarded' => 'récompensé', 'planned' => 'prévu', 'postponed' => 'reporté', 'adopted' => 'adopté', 'extended' => 'prolongé',
        'redeemed' => 'utilisé', 'purchased' => 'acheté', 'migrated' => 'appliqué', 'used' => 'utilisé', 'restored' => 'restaurée', 'and' => 'et',
    ];

    private static function phrase(string $code): string
    {
        $words = preg_split('/[_-]/', $code);
        $last = array_pop($words);

        return trim(implode(' ', array_filter(array_map(fn ($w) => self::WORDS[$w] ?? $w, $words))).' '.(self::VERBS[$last] ?? self::WORDS[$last] ?? $last));
    }

    /** Catégories du filtre (premier mot du code). */
    public const CATEGORIES = [
        'horses' => 'Chevaux (fiche, soins, suivi, partage…)', 'sessions' => 'Séances', 'calendar' => 'Agenda', 'budget' => 'Budget',
        'auth' => 'Connexions', 'sharing' => 'Partages reçus', 'invitations' => 'Invitations', 'organization' => 'Espace et membres', 'billing' => 'Abonnement',
        'settings' => 'Paramètres', 'assistant' => 'Assistant', 'professionals' => 'Intervenants', 'boarding' => 'Pension', 'half-leases' => 'Demi-pensions',
        'admin' => 'Administration',
    ];
}
