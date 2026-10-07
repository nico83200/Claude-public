<?php
declare(strict_types=1);

/**
 * Licence NLapps : l'installation vérifie régulièrement auprès du centre d'assistance son abonnement, l'option IA,
 * la dernière version publiée et la FAQ partagée. Sans clé NLapps (installation autonome), rien n'est restreint.
 *
 * Statuts : unmanaged (pas de clé), active, grace (échéance passée, délai de grâce), expired, suspended, invalid (clé refusée).
 */

const LICENCE_CHECK_EVERY = 21600; // 6 heures

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

function licence_status(): string
{
    return (string)(licence_info()['status'] ?? 'unknown');
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
        'stats' => licence_stats(), 'faq_hash' => (string)setting('faq_remote_hash', ''),
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
    return $state;
}

/** L'option assistant IA est-elle couverte par la licence ? (toujours vrai sans clé NLapps) */
function licence_ai_allowed(): bool
{
    $i = licence_info();
    return match ($i['status'] ?? 'unknown') {
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
    return licence_status() === 'suspended';
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
    $date = fn($d) => $d ? date('d/m/Y', strtotime((string)$d)) : '';
    return match ($i['status'] ?? 'unknown') {
        'active' => isset($i['days_left']) && $i['days_left'] !== null && $i['days_left'] <= 15
            ? ['level' => 'info', 'text' => 'Votre abonnement Approvia arrive à échéance le ' . $date($i['paid_until']) . '.' . $msg . ' ' . $contact] : ($msg ? ['level' => 'info', 'text' => trim($msg)] : null),
        'grace' => ['level' => 'warn', 'text' => 'Abonnement échu le ' . $date($i['paid_until']) . ' : l\'application reste utilisable jusqu\'au ' . $date($i['grace_until']) . ', puis l\'assistant IA et les mises à jour seront coupés.' . $msg . ' ' . $contact],
        'expired' => ['level' => 'danger', 'text' => 'Abonnement expiré : assistant IA et mises à jour désactivés.' . $msg . ' ' . $contact],
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
