<?php
declare(strict_types=1);

/**
 * Démo publique : connexion en un clic avec chacun des quatre rôles, données fictives remises à zéro
 * chaque nuit, actions sensibles neutralisées (mots de passe, paramètres, e-mails, suppression des données).
 * Activée par la console NLapps (case « Démo publique ») ou, pour une installation simple, par 'demo_mode' => true dans config.php.
 */

/** Profils proposés sur la page de connexion : rôle => [e-mail, libellé, ce qu'on y découvre, icône]. */
const DEMO_PROFILES = [
    'user'    => ['claire.secretaire@demo.fr', 'Salarié', 'Commander, suivre ses demandes, réceptionner, compter le stock', 'cart'],
    'manager' => ['sophie.responsable@demo.fr', 'Responsable de centre', 'Valider les demandes de son centre au-delà du seuil', 'check-circle'],
    'buyer'   => ['acheteur@demo.fr', 'Acheteur', 'Traiter les demandes, passer les commandes, gérer contrats et factures', 'truck'],
    'admin'   => ['admin@demo.fr', 'Administrateur', 'Tout, y compris les centres, les comptes et les paramètres', 'settings'],
];

/** Heure de la remise à zéro quotidienne. */
const DEMO_RESET_HOUR = 3;

/** Appels à l'assistant IA par jour sur la démo (la clé est celle de NLapps). */
const DEMO_AI_DAILY = 150;

function demo_mode(): bool
{
    return !empty(current_instance_info()['public_demo']) || (bool)cfg('demo_mode', false);
}

/** Pages dont les envois de formulaire sont neutralisés sur la démo, avec l'explication affichée. */
function demo_blocked(string $route): ?string
{
    if (!demo_mode() || !is_post()) {
        return null;
    }
    $rules = [
        '#^(profile|register|forgot|reset)$#' => 'Sur la démo, les mots de passe, la double authentification et les inscriptions sont désactivés.',
        '#^admin/(settings|rgpd|cleanup|updates.*|backup-daily|mail-queue|videos|transfer)$#' => 'Sur la démo, les paramètres, sauvegardes et suppressions de données sont en lecture seule.',
        '#^admin/(user|users/delete)$#' => 'Sur la démo, les comptes de démonstration ne peuvent pas être modifiés : utilisez les connexions en un clic.',
    ];
    foreach ($rules as $re => $msg) {
        if (preg_match($re, $route)) {
            return $msg;
        }
    }
    return null;
}

/** Connexion en un clic avec un profil de démonstration. */
function demo_login(): void
{
    if (!demo_mode()) {
        abort(404);
    }
    $role = (string)input('as');
    $email = DEMO_PROFILES[$role][0] ?? null;
    $u = $email ? one("SELECT * FROM users WHERE email = ? AND status = 'active'", [$email]) : null;
    if (!$u) {
        flash('error', 'Ce profil de démonstration est momentanément indisponible (remise à zéro en cours) : réessayez dans une minute.');
        redirect('login');
    }
    login_user($u);
    $_SESSION['demo_profile'] = $role;
    redirect(is_admin($u) ? 'admin' : 'dashboard');
}

/** Quota quotidien d'appels IA sur la démo ; renvoie false quand il est atteint. */
function demo_ai_allowed(): bool
{
    if (!demo_mode()) {
        return true;
    }
    $day = date('Y-m-d');
    [$d, $n] = array_pad(explode('|', (string)setting('demo_ai_count', '')), 2, '0');
    $n = $d === $day ? (int)$n : 0;
    if ($n >= DEMO_AI_DAILY) {
        return false;
    }
    set_setting('demo_ai_count', $day . '|' . ($n + 1));
    return true;
}

/**
 * Remise à zéro : toutes les données sont effacées puis les données de démonstration recréées
 * (réglages techniques, licence et tutoriels vidéo conservés). Fichiers envoyés effacés.
 */
function demo_reset(): void
{
    require_once APP . '/install_demo.php';
    $keepTables = ['settings', 'videos'];
    $settings = all('SELECT skey, svalue FROM settings');
    $mysql = db_driver() === 'mysql';
    db()->exec($mysql ? 'SET FOREIGN_KEY_CHECKS = 0' : 'PRAGMA foreign_keys = OFF');
    try {
        foreach (db_tables() as $t) {
            if (!in_array($t, $keepTables, true)) {
                db()->exec($mysql ? "TRUNCATE TABLE $t" : "DELETE FROM $t");
            }
        }
        if (!$mysql) {
            try {
                db()->exec("DELETE FROM sqlite_sequence WHERE name NOT IN ('settings', 'videos')");
            } catch (Throwable) {
            }
        }
        q('DELETE FROM settings');
    } finally {
        db()->exec($mysql ? 'SET FOREIGN_KEY_CHECKS = 1' : 'PRAGMA foreign_keys = ON');
    }
    // Réglages rétablis tels quels (clé cron, licence, versions…), sauf ceux que les données de démo réécrivent
    foreach ($settings as $s) {
        if (!in_array($s['skey'], ['company_name', 'company_address', 'approval_threshold', 'demo_ai_count'], true)) {
            insert('settings', $s);
        }
    }
    setting('', null, true);
    foreach (['invoices', 'contracts', 'imports', 'reports', 'mail'] as $d) {
        foreach (glob(storage_path($d) . '/*') ?: [] as $f) {
            is_file($f) && @unlink($f);
        }
    }
    foreach (['products', 'brand'] as $d) {
        foreach (glob(uploads_path($d) . '/*') ?: [] as $f) {
            is_file($f) && @unlink($f);
        }
    }
    tx(function () {
        $hash = password_hash(bin2hex(random_bytes(12)), PASSWORD_DEFAULT); // connexion en un clic uniquement
        $adminId = insert('users', ['email' => DEMO_PROFILES['admin'][0], 'password_hash' => $hash, 'first_name' => 'Camille', 'last_name' => 'Durand',
            'job' => 'Directrice des achats', 'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
        install_base_data();
        install_demo_data($adminId);
    });
    set_setting('mail_enabled', '0');
    set_setting('demo_reset_at', now());
}

/** Tâche planifiée : remise à zéro une fois par nuit, à partir de DEMO_RESET_HOUR. */
function cron_demo_reset(bool $force = false): int
{
    if (!demo_mode()) {
        return 0;
    }
    $last = (string)setting('demo_reset_at', '');
    $due = (int)date('G') >= DEMO_RESET_HOUR && substr($last, 0, 10) !== date('Y-m-d');
    if (!$due && !($force && $last === '')) {
        return 0;
    }
    demo_reset();
    return 1;
}
