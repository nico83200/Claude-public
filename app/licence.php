<?php
declare(strict_types=1);

/**
 * Licence Centriva : gérée par la console de la plateforme (super administrateurs) et lue directement par chaque espace.
 * Une installation autonome (hors plateforme) n'a pas de licence : rien n'est restreint. Le centre d'assistance NLapps
 * ne gère plus les licences (il ne sert qu'au chat).
 *
 * Statuts : unmanaged (hors plateforme), active, grace (échéance passée, délai de grâce), expired, suspended.
 */

const LICENCE_CHECK_EVERY = 600; // 10 minutes : une licence expirée ou suspendue est appliquée sans délai

function licence_managed(): bool
{
    return licence_platform(); // hors plateforme : aucune licence (le centre d'assistance ne sert plus qu'au chat)
}

/** Espace de la plateforme multi-clients : la licence est gérée dans la console (aucun appel réseau). */
function licence_platform(): bool
{
    return current_instance() !== null && instances_enabled();
}

/** Dernier état connu (mis en cache dans les paramètres ; lu directement dans la console pour un espace de la plateforme). */
function licence_info(): array
{
    if (licence_platform()) {
        static $cache = [];
        $slug = (string)current_instance();
        return $cache[$slug] ??= platform_licence($slug) + ['billing' => platform_billing_summary($slug), 'latest' => null, 'checked_at' => now()];
    }
    if (!licence_managed()) {
        return ['status' => 'unmanaged'];
    }
    $c = json_decode((string)setting('licence_cache', ''), true);
    return is_array($c) ? $c : ['status' => 'unknown'];
}

/**
 * Statut effectif : l'échéance est contrôlée localement chaque jour, sans attendre la prochaine vérification
 * auprès du centre d'assistance (le lendemain de la date « payé jusqu'au », ou après le délai de grâce s'il y en a un).
 */
function licence_status(?string $today = null): string
{
    $i = licence_info();
    $status = (string)($i['status'] ?? 'unknown');
    $today ??= date('Y-m-d');
    if (in_array($status, ['active', 'grace'], true) && !empty($i['paid_until']) && $today > $i['paid_until']) {
        $grace = (string)($i['grace_until'] ?? '');
        $status = $grace !== '' && $grace > $i['paid_until'] && $today <= $grace ? 'grace' : 'expired';
    }
    return $status;
}

/** Statistiques d'usage transmises au centre d'assistance (aucune donnée personnelle). */
function licence_stats(): array
{
    return [
        'users' => (int)val("SELECT COUNT(*) FROM users WHERE status = 'active' AND deleted_at IS NULL"),
        'centers' => (int)val('SELECT COUNT(*) FROM centers WHERE active = 1'),
        'orders30' => (int)val('SELECT COUNT(*) FROM purchase_orders WHERE created_at >= ?', [date('Y-m-d H:i:s', strtotime('-30 days'))]),
        'products' => (int)val('SELECT COUNT(*) FROM products WHERE active = 1'),
    ];
}

/** Adresse publique de l'installation, d'après la dernière requête web (le cron n'en a pas). */
function licence_instance_url(): string
{
    if (!empty($_SERVER['HTTP_HOST'])) {
        $u = instance_public_url() ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/');
        if (setting('instance_url') !== $u) {
            set_setting('instance_url', $u);
        }
        return $u;
    }
    return (string)setting('instance_url', '');
}

/**
 * Vérification auprès du centre d'assistance. Hors connexion, le dernier état connu reste valable :
 * une coupure réseau ne bloque jamais l'application.
 */
function licence_check(bool $force = false): array
{
    if (!licence_managed()) {
        return ['status' => 'unmanaged'];
    }
    return licence_info();
}

/** L'option assistant IA est-elle couverte par la licence ? (toujours vrai sans clé NLapps) */
function licence_ai_allowed(): bool
{
    $i = licence_info();
    return match (licence_status()) {
        'unmanaged', 'unknown' => true,
        'active', 'grace' => !empty($i['ai']),
        default => false,
    };
}

function licence_updates_allowed(): bool
{
    return in_array(licence_status(), ['active', 'grace'], true);
}

/** Accès suspendu par NLapps : seuls les administrateurs peuvent encore se connecter (pour régulariser). */
function licence_blocked(): bool
{
    return in_array(licence_status(), ['expired', 'suspended'], true);
}

/**
 * Accès coupé : avant de refuser, on revérifie auprès du centre d'assistance (au plus une fois par minute),
 * pour que le renouvellement fait par NLapps soit pris en compte aussitôt.
 */
function licence_blocked_now(): bool
{
    if (!licence_blocked()) {
        return false;
    }
    if ((int)setting('licence_recheck_at', '0') < time() - 60) {
        set_setting('licence_recheck_at', (string)time());
        licence_check(true);
    }
    return licence_blocked();
}

/** Version publiée plus récente que celle installée, téléchargeable. */
function licence_update_available(): ?array
{
    $l = licence_info()['latest'] ?? null;
    return is_array($l) && !empty($l['version']) && version_compare((string)$l['version'], APP_VERSION, '>') ? $l : null;
}

/** Bandeau pour les administrateurs : échéance proche, impayé, suspension, clé refusée. */
function licence_notice(): ?array
{
    $i = licence_info();
    $contact = 'Contactez ' . (support_contact()['editor']) . ' (' . support_contact()['email'] . ').';
    $msg = !empty($i['message']) ? ' ' . $i['message'] : '';
    $i['status'] = licence_status();
    $date = fn($d) => $d ? date('d/m/Y', strtotime((string)$d)) : '';
    $pay = !licence_autopay() && licence_pay_url() ? ['pay_url' => licence_pay_url()] : [];
    $n = match ($i['status'] ?? 'unknown') {
        'active' => !empty($i['paid_until']) && !licence_autopay() && (strtotime((string)$i['paid_until']) - strtotime(date('Y-m-d'))) / 86400 <= 15
            ? ['level' => 'info', 'text' => 'Votre abonnement Centriva arrive à échéance le ' . $date($i['paid_until']) . ' : sans renouvellement, l\'accès sera coupé le lendemain.' . $msg . ' ' . $contact] : ($msg ? ['level' => 'info', 'text' => trim($msg)] : null),
        'grace' => ['level' => 'warn', 'text' => 'Abonnement échu le ' . $date($i['paid_until']) . ' : l\'accès au logiciel sera coupé le ' . $date(date('Y-m-d', strtotime((string)$i['grace_until'] . ' +1 day'))) . ' (tous les utilisateurs seront déconnectés).' . $msg . ' ' . $contact],
        'expired' => ['level' => 'danger', 'text' => 'Licence expirée : l\'accès au logiciel est coupé.' . $msg . ' ' . $contact],
        'suspended' => ['level' => 'danger', 'text' => 'Accès suspendu par ' . support_contact()['editor'] . ' : les utilisateurs ne peuvent plus se connecter.' . $msg . ' ' . $contact],
        'invalid' => ['level' => 'warn', 'text' => 'La clé NLapps (Paramètres → Licence et assistance) est refusée par le centre d\'assistance. ' . $contact],
        default => null,
    };
    return $n ? $n + $pay : null;
}

/**
 * Paiement en ligne de l'abonnement (centre d'assistance NLapps relié à Stripe) : lien vers la page de paiement
 * propre à cette installation, ou null si NLapps ne propose pas le paiement en ligne.
 */
function licence_pay_url(): ?string
{
    if (licence_platform()) {
        return url('admin/subscription'); // abonnement géré par l'établissement lui-même
    }
    $b = licence_info()['billing'] ?? null;
    $u = is_array($b) && !empty($b['online']) ? (string)($b['pay_url'] ?? '') : '';
    return preg_match('#^https?://#', $u) ? $u : null;
}

/** Les paiements sont-ils automatiques (abonnement en ligne en place) ? */
function licence_autopay(): bool
{
    return !empty(licence_info()['billing']['active']);
}
