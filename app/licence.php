<?php
declare(strict_types=1);

/**
 * Licence NLapps : l'installation vérifie régulièrement auprès du centre d'assistance son abonnement, l'option IA,
 * la dernière version publiée et la FAQ partagée. Sans clé NLapps (installation autonome), rien n'est restreint.
 *
 * Statuts : unmanaged (pas de clé), active, grace (échéance passée, délai de grâce), expired, suspended, invalid (clé refusée).
 */

const LICENCE_CHECK_EVERY = 600; // 10 minutes : une licence expirée ou suspendue est appliquée sans délai

function licence_managed(): bool
{
    return support_live_enabled();
}

/** Dernier état connu (mis en cache dans les paramètres). */
function licence_info(): array
{
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
        $u = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';
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
    $cached = licence_info();
    if (!$force && !empty($cached['checked_at']) && strtotime($cached['checked_at']) > time() - LICENCE_CHECK_EVERY) {
        return $cached;
    }
    $r = support_hub('check', [], [
        'app' => 'approvia', 'version' => APP_VERSION, 'url' => licence_instance_url(), 'php' => PHP_VERSION,
        'stats' => licence_stats(), 'faq_hash' => (string)setting('faq_remote_hash', ''), 'videos_hash' => videos_remote_hash(),
    ]);
    if ($r === null) {
        $code = support_hub_last_code();
        $state = $cached;
        if ($code === 401) {
            $state = ['status' => 'invalid', 'checked_at' => now()];
        }
        $state['last_error'] = $code ? 'HTTP ' . $code : 'centre d\'assistance injoignable';
        $state['last_attempt'] = now();
        set_setting('licence_cache', json_encode($state, JSON_UNESCAPED_UNICODE));
        return $state;
    }
    $lic = (array)($r['licence'] ?? []);
    $state = [
        'status' => in_array($lic['status'] ?? '', ['active', 'grace', 'expired', 'suspended'], true) ? $lic['status'] : 'active',
        'plan' => (string)($lic['plan'] ?? ''), 'paid_until' => $lic['paid_until'] ?? null, 'grace_until' => $lic['grace_until'] ?? null,
        'days_left' => isset($lic['days_left']) ? (int)$lic['days_left'] : null, 'ai' => !empty($lic['ai']),
        'message' => (string)($lic['message'] ?? ''), 'contact' => (array)($lic['contact'] ?? []),
        'latest' => is_array($r['latest'] ?? null) ? $r['latest'] : null,
        'checked_at' => now(),
    ];
    set_setting('licence_cache', json_encode($state, JSON_UNESCAPED_UNICODE));
    if (isset($r['faq']['items']) && is_array($r['faq']['items'])) {
        set_setting('faq_remote', json_encode($r['faq']['items'], JSON_UNESCAPED_UNICODE));
        set_setting('faq_remote_hash', (string)($r['faq']['hash'] ?? ''));
    }
    // Tutoriels vidéo publiés par NLapps : liste mise à jour ici, fichiers téléchargés par la tâche planifiée « videos »
    if (isset($r['videos']['items']) && is_array($r['videos']['items'])) {
        videos_sync_remote($r['videos']['items']);
        set_setting('videos_remote_hash', (string)($r['videos']['hash'] ?? ''));
    }
    return $state;
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
    return match ($i['status'] ?? 'unknown') {
        'active' => !empty($i['paid_until']) && (strtotime((string)$i['paid_until']) - strtotime(date('Y-m-d'))) / 86400 <= 15
            ? ['level' => 'info', 'text' => 'Votre abonnement Approvia arrive à échéance le ' . $date($i['paid_until']) . ' : sans renouvellement, l\'accès sera coupé le lendemain.' . $msg . ' ' . $contact] : ($msg ? ['level' => 'info', 'text' => trim($msg)] : null),
        'grace' => ['level' => 'warn', 'text' => 'Abonnement échu le ' . $date($i['paid_until']) . ' : l\'accès au logiciel sera coupé le ' . $date(date('Y-m-d', strtotime((string)$i['grace_until'] . ' +1 day'))) . ' (tous les utilisateurs seront déconnectés).' . $msg . ' ' . $contact],
        'expired' => ['level' => 'danger', 'text' => 'Licence expirée : l\'accès au logiciel est coupé.' . $msg . ' ' . $contact],
        'suspended' => ['level' => 'danger', 'text' => 'Accès suspendu par ' . support_contact()['editor'] . ' : les utilisateurs ne peuvent plus se connecter.' . $msg . ' ' . $contact],
        'invalid' => ['level' => 'warn', 'text' => 'La clé NLapps (Paramètres → Licence et assistance) est refusée par le centre d\'assistance. ' . $contact],
        default => null,
    };
}

/** Télécharge la version proposée par le centre d'assistance et la prépare pour l'installation (vérification SHA-256). */
function licence_download_update(string $dest): array
{
    $l = licence_update_available();
    if (!$l) {
        throw new RuntimeException('Aucune nouvelle version disponible.');
    }
    if (!licence_updates_allowed()) {
        throw new RuntimeException('Licence non à jour : les mises à jour sont indisponibles.');
    }
    $code = support_hub_download('download', ['v' => $l['version']], $dest);
    if ($code !== 200) {
        @unlink($dest);
        throw new RuntimeException('Téléchargement impossible (HTTP ' . $code . ').');
    }
    if (!empty($l['sha256']) && !hash_equals((string)$l['sha256'], (string)hash_file('sha256', $dest))) {
        @unlink($dest);
        throw new RuntimeException('Paquet corrompu pendant le téléchargement (empreinte différente) : réessayez.');
    }
    return $l;
}
