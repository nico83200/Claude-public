<?php
declare(strict_types=1);

/**
 * Administration de la plateforme Centriva (super administrateurs NLapps) : clients, mises à jour, vidéos communes,
 * comptes super administrateur. Chaque client a sa base de données, ses fichiers, ses comptes et sa licence.
 *
 * Premier lancement : création du premier compte super administrateur avec le code d'installation déposé dans
 * storage/console-code.txt (lisible uniquement par FTP / gestionnaire de fichiers), et reprise des données existantes
 * comme premier client (IMSS). Ensuite, connexion par e-mail + mot de passe (+ double authentification), ici ou sur la
 * page de connexion commune de centriva.fr.
 */
define('NL_CONSOLE', true);
require __DIR__ . '/app/bootstrap.php';
require APP . '/instances_admin.php';

central_session();
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

@mkdir(ROOT . '/storage', 0750, true);
const CONSOLE_AUTH = ROOT . '/storage/console-auth.json';   // ancienne console (mot de passe unique), reprise au premier accès
const CONSOLE_CODE = ROOT . '/storage/console-code.txt';

/** Adresse d'un espace : son adresse dédiée s'il en a une, sinon https://<ce serveur>/<identifiant>/. */
function console_space_url(string $slug, array $i): string
{
    if (!empty($i['hosts'][0])) {
        return 'https://' . $i['hosts'][0] . '/';
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'centriva.fr') . instance_web_dir() . '/' . $slug . '/';
}

function console_licence_tag(string $status): string
{
    [$label, $cls] = ['active' => ['licence active', 'green'], 'grace' => ['délai de grâce', 'amber'], 'expired' => ['licence expirée', 'red'], 'suspended' => ['licence suspendue', 'red']][$status] ?? [$status, ''];
    return '<span class="tag ' . $cls . '">' . e($label) . '</span>';
}

function console_flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function console_go(string $page = '', array $q = []): never
{
    header('Location: console.php' . ($page || $q ? '?' . http_build_query(['p' => $page ?: null] + $q) : ''));
    exit;
}

$me = central_superadmin();
$logged = (bool)$me;
$page = (string)($_GET['p'] ?? '');
$post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
if ($post) {
    csrf_check();
}
$error = null;
$legacy = is_file(CONSOLE_AUTH) ? (json_decode((string)file_get_contents(CONSOLE_AUTH), true) ?: []) : [];

// ------------------------------------------------------------- Premier lancement : premier compte super administrateur
if (!superadmins()) {
    if (!$legacy && !is_file(CONSOLE_CODE)) {
        file_put_contents(CONSOLE_CODE, strtoupper(bin2hex(random_bytes(4))) . "\n", LOCK_EX);
        @chmod(CONSOLE_CODE, 0600);
    }
    if ($post) {
        $pw = (string)($_POST['password'] ?? '');
        $proof = trim((string)($_POST['code'] ?? ''));
        $proofOk = $legacy ? password_verify($proof, (string)($legacy['hash'] ?? '')) : hash_equals(trim((string)@file_get_contents(CONSOLE_CODE)), strtoupper($proof));
        try {
            if (central_throttled()) {
                throw new RuntimeException('Trop d\'essais : réessayez dans un quart d\'heure.');
            }
            if (!$proofOk) {
                central_throttled(true);
                throw new RuntimeException($legacy ? 'Mot de passe de l\'ancienne console incorrect.' : 'Code d\'installation incorrect.');
            }
            if (mb_strlen($pw) < 12 || $pw !== (string)($_POST['confirm'] ?? '')) {
                throw new RuntimeException('Mot de passe : 12 caractères minimum, saisi deux fois à l\'identique.');
            }
            $a = superadmin_save(['name' => trim((string)($_POST['name'] ?? '')), 'email' => (string)($_POST['email'] ?? ''), 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'totp' => null]);
            // Données existantes : elles deviennent le premier client
            if (!empty($_POST['adopt']) && is_file(ROOT . '/config.php') && !instances_registry()) {
                instance_adopt_current(strtolower(trim((string)($_POST['adopt_slug'] ?? 'imss'))) ?: 'imss', trim((string)($_POST['adopt_name'] ?? '')) ?: 'IMSS', '');
            }
            @unlink(CONSOLE_CODE);
            @unlink(CONSOLE_AUTH);
            central_superadmin_login($a);
            console_flash('ok', 'Compte super administrateur créé.' . (instances_registry() ? ' Les données existantes forment le client « ' . array_values(instances_registry())[0]['name'] . ' ».' : ''));
            console_go();
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
    $page = 'setup';
} elseif (!$logged && instances_enabled() && !is_file(ROOT . '/config.php') && !$post) {
    // Une seule page de connexion pour tout le monde (centriva.fr) : le super administrateur y est reconnu
    header('Location: ' . instance_web_dir() . '/?changer=1');
    exit;
} elseif (!$logged) {
    // ------------------------------------------------------------- Connexion
    if ($post && isset($_POST['totp']) && ($p = $_SESSION['super_pending'] ?? null) && time() - (int)$p['at'] < 300 && ($a = superadmin_get((string)$p['id']))) {
        if (!central_throttled() && totp_match((string)$a['totp'], (string)$_POST['totp']) !== null) {
            unset($_SESSION['super_pending']);
            central_superadmin_login($a);
            console_go();
        }
        central_throttled(true);
        $error = 'Code incorrect.';
        $page = 'totp';
    } elseif ($post) {
        $a = superadmin_find((string)($_POST['email'] ?? ''));
        if (central_throttled()) {
            $error = 'Trop d\'essais : réessayez dans un quart d\'heure.';
        } elseif ($a && password_verify((string)($_POST['password'] ?? ''), (string)$a['hash'])) {
            if (!empty($a['totp'])) {
                session_regenerate_id(true);
                $_SESSION['super_pending'] = ['id' => $a['id'], 'at' => time()];
                $page = 'totp';
            } else {
                central_superadmin_login($a);
                console_go();
            }
        } else {
            central_throttled(true);
            $error = 'Identifiants incorrects.';
        }
    }
    if ($page !== 'totp') {
        $page = 'login';
    }
} elseif ($page === 'logout') {
    unset($_SESSION['super_id'], $_SESSION['super_hash']);
    session_regenerate_id(true);
    header('Location: ' . (is_file(ROOT . '/config.php') ? 'console.php' : instance_web_dir() . '/?changer=1'));
    exit;
}

// ------------------------------------------------------------- Actions (connecté)
if ($logged && $post) {
    $action = (string)($_POST['action'] ?? '');
    $slug = (string)($_POST['slug'] ?? '');
    try {
        switch ($action) {
            case 'create':
                $s = instance_create([
                    'slug' => $_POST['slug'] ?? '', 'name' => $_POST['name'] ?? '', 'hosts' => $_POST['hosts'] ?? '', 'app_name' => $_POST['app_name'] ?? '',
                    'admin_first_name' => $_POST['admin_first_name'] ?? '', 'admin_last_name' => $_POST['admin_last_name'] ?? '',
                    'admin_email' => $_POST['admin_email'] ?? '', 'admin_password' => $_POST['admin_password'] ?? '',
                    'demo' => !empty($_POST['demo']), 'public_demo' => !empty($_POST['public_demo']), 'hub_url' => $_POST['hub_url'] ?? '', 'hub_key' => $_POST['hub_key'] ?? '',
                    'db' => ['driver' => $_POST['db_driver'] ?? 'sqlite', 'host' => $_POST['db_host'] ?? '', 'port' => $_POST['db_port'] ?? '',
                        'name' => $_POST['db_name'] ?? '', 'user' => $_POST['db_user'] ?? '', 'pass' => $_POST['db_pass'] ?? ''],
                ]);
                platform_licence_save($s, ['contact_email' => mb_strtolower(trim((string)($_POST['admin_email'] ?? ''))), 'ai' => !empty($_POST['ai'])]);
                $hubMsg = '';
                if (platform_hub_linked() && empty($_POST['public_demo'])) {
                    try {
                        platform_hub_provision($s);
                        $hubMsg = ' Conversation en direct avec l\'assistance activée.';
                    } catch (Throwable $e) {
                        $hubMsg = ' Assistance non reliée (' . $e->getMessage() . ') : bouton « Relier à l\'assistance » sur sa fiche.';
                    }
                }
                console_flash('ok', 'Espace « ' . instances_registry()[$s]['name'] . ' » créé. L\'administrateur se connecte à ' . console_space_url($s, instances_registry()[$s]) . ' (identifiant de l\'espace : ' . $s . ').' . $hubMsg);
                console_go();

            case 'adopt':
                $s = instance_adopt_current((string)($_POST['slug'] ?? ''), trim((string)($_POST['name'] ?? '')), (string)($_POST['hosts'] ?? ''));
                console_flash('ok', 'L\'installation existante est devenue l\'espace « ' . $s . ' » : comptes, commandes et réglages conservés.');
                console_go();

            case 'hosts':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $reg[$slug]['hosts'] = instance_parse_hosts((string)($_POST['hosts'] ?? ''), $slug);
                $reg[$slug]['name'] = trim((string)($_POST['name'] ?? '')) ?: $reg[$slug]['name'];
                instances_save($reg);
                platform_hub_client_state($slug, empty($reg[$slug]['suspended']), $reg[$slug]['name']);
                console_flash('ok', 'Espace « ' . $reg[$slug]['name'] . ' » mis à jour.');
                console_go();

            case 'suspend':
            case 'resume':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $reg[$slug]['suspended'] = $action === 'suspend';
                instances_save($reg);
                platform_hub_client_state($slug, $action !== 'suspend');
                console_flash('ok', $action === 'suspend' ? 'Espace « ' . $reg[$slug]['name'] . ' » suspendu : plus personne ne peut s\'y connecter.' : 'Espace « ' . $reg[$slug]['name'] . ' » rétabli.');
                console_go();

            case 'demo_toggle':
                $reg = instances_registry();
                if (!isset($reg[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $on = empty($reg[$slug]['public_demo']);
                if ($on && empty($reg[$slug]['demo'])) {
                    throw new RuntimeException('Seul un espace créé avec les données de démonstration peut devenir une démo publique : ses données seraient effacées chaque nuit.');
                }
                $reg[$slug]['public_demo'] = $on;
                instances_save($reg);
                if ($on) {
                    instance_demo_reset($slug);
                }
                console_flash('ok', $on ? 'Démo publique activée : connexion en un clic, remise à zéro chaque nuit à 3 h.' : 'Démo publique désactivée.');
                console_go();

            case 'demo_reset':
                instance_demo_reset($slug);
                console_flash('ok', 'Démo « ' . instances_registry()[$slug]['name'] . ' » remise à zéro.');
                console_go();

            case 'delete':
                if (trim((string)($_POST['confirm'] ?? '')) !== $slug) {
                    throw new RuntimeException('Pour supprimer, saisissez exactement l\'identifiant du client (' . $slug . ').');
                }
                platform_hub_client_state($slug, false);
                $archive = instance_delete($slug);
                platform_licence_delete($slug);
                console_flash('ok', 'Espace supprimé. Archive (base et fichiers) : storage/clients-supprimes/' . $archive . '. Une base MySQL dédiée reste à supprimer chez l\'hébergeur.');
                console_go();

            case 'update':
                $f = $_FILES['package'] ?? null;
                if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('Choisissez le paquet de mise à jour (centriva-x.y.z.zip).');
                }
                $tmp = ROOT . '/storage/console-update.zip';
                move_uploaded_file($f['tmp_name'], $tmp);
                $info = instances_update_code($tmp);
                platform_release_from_package($tmp, update_inspect($tmp), $me['name']);
                @unlink($tmp);
                console_flash('ok', 'Code mis à jour en version ' . $info['version'] . ' (sauvegarde : ' . $info['code_backup'] . ', bases sauvegardées dans chaque espace).');
                console_go('migrate'); // nouvelle requête : la migration s'exécute avec le nouveau code

            case 'migrate':
                $res = instances_migrate_all();
                $ko = array_filter($res, fn($r) => $r !== 'ok');
                console_flash($ko ? 'error' : 'ok', $ko ? 'Migration en échec : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($ko), $ko)) : plural(count($res), 'base migrée', 'bases migrées') . ' en version ' . APP_VERSION . '.');
                console_go();

            case 'cron':
                $res = instances_cron_all(!empty($_POST['force']));
                console_flash('ok', 'Tâches planifiées : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($res), $res)));
                console_go();

            case 'password':
                if (!password_verify((string)($_POST['current'] ?? ''), (string)$me['hash'])) {
                    throw new RuntimeException('Mot de passe actuel incorrect.');
                }
                $pw = (string)($_POST['new'] ?? '');
                if (mb_strlen($pw) < 12 || $pw !== (string)($_POST['confirm'] ?? '')) {
                    throw new RuntimeException('Nouveau mot de passe : 12 caractères minimum, saisi deux fois à l\'identique.');
                }
                $me['hash'] = password_hash($pw, PASSWORD_DEFAULT);
                superadmin_save($me);
                central_superadmin_login($me);
                console_flash('ok', 'Mot de passe modifié.');
                console_go('account');

            case 'totp_enable':
                $secret = (string)($_SESSION['totp_setup'] ?? '');
                if ($secret === '' || totp_match($secret, (string)($_POST['code'] ?? '')) === null) {
                    throw new RuntimeException('Code incorrect : vérifiez l\'heure du téléphone et réessayez.');
                }
                $me['totp'] = $secret;
                superadmin_save($me);
                unset($_SESSION['totp_setup']);
                console_flash('ok', 'Double authentification activée : un code vous sera demandé à chaque connexion.');
                console_go('account');

            case 'totp_disable':
                if (!password_verify((string)($_POST['current'] ?? ''), (string)$me['hash'])) {
                    throw new RuntimeException('Mot de passe incorrect.');
                }
                $me['totp'] = null;
                superadmin_save($me);
                console_flash('ok', 'Double authentification désactivée.');
                console_go('account');

            case 'admin_add':
                $pw = (string)($_POST['password'] ?? '');
                if (mb_strlen($pw) < 12) {
                    throw new RuntimeException('Mot de passe provisoire : 12 caractères minimum.');
                }
                $n = superadmin_save(['name' => trim((string)($_POST['name'] ?? '')), 'email' => (string)($_POST['email'] ?? ''), 'hash' => password_hash($pw, PASSWORD_DEFAULT), 'totp' => null]);
                console_flash('ok', 'Compte super administrateur créé pour ' . $n['name'] . ' (' . $n['email'] . ').');
                console_go('admins');

            case 'admin_delete':
                $id = (string)($_POST['id'] ?? '');
                if ($id === $me['id']) {
                    throw new RuntimeException('Vous ne pouvez pas supprimer votre propre compte.');
                }
                superadmin_delete($id);
                console_flash('ok', 'Compte supprimé.');
                console_go('admins');

            case 'video_upload':
            case 'video_save':
                $list = central_videos();
                $meta = [
                    'title' => mb_substr(trim((string)($_POST['title'] ?? '')), 0, 150),
                    'description' => mb_substr(trim((string)($_POST['description'] ?? '')), 0, 2000) ?: null,
                    'keywords' => mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 400) ?: null,
                    'chapters' => video_chapters_parse((string)($_POST['chapters'] ?? '')),
                    'audience' => ($_POST['audience'] ?? '') === 'admin' ? 'admin' : 'all',
                    'position' => (int)($_POST['position'] ?? 0),
                    'welcome' => !empty($_POST['welcome']),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                if ($meta['title'] === '') {
                    throw new RuntimeException('Indiquez le titre de la vidéo.');
                }
                if ($meta['welcome']) { // une seule vidéo d'accueil
                    $list = array_map(fn($v) => ['welcome' => false] + $v, $list);
                }
                if ($action === 'video_upload') {
                    $f = $_FILES['video'] ?? null;
                    if (!$f || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
                        throw new RuntimeException('Choisissez le fichier vidéo (MP4). Fichiers volumineux : vérifiez upload_max_filesize chez l\'hébergeur.');
                    }
                    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
                    if (!in_array($mime, ['video/mp4', 'video/x-m4v', 'video/quicktime', 'application/mp4'], true)) {
                        throw new RuntimeException('Format non pris en charge (' . $mime . ') : envoyez une vidéo MP4 (H.264).');
                    }
                    $uid = 'c-' . bin2hex(random_bytes(5));
                    move_uploaded_file($f['tmp_name'], central_videos_dir() . '/' . $uid . '.mp4');
                    $list[] = $meta + ['uid' => $uid, 'file' => $uid . '.mp4', 'size' => filesize(central_videos_dir() . '/' . $uid . '.mp4'),
                        'duration' => (int)($_POST['duration'] ?? 0) ?: null, 'published' => !empty($_POST['publish']), 'created_at' => date('Y-m-d H:i:s')];
                    console_flash('ok', 'Vidéo « ' . $meta['title'] . ' » ' . (!empty($_POST['publish']) ? 'publiée pour tous les clients.' : 'enregistrée (brouillon).'));
                } else {
                    foreach ($list as &$v) {
                        if ($v['uid'] === ($_POST['uid'] ?? '')) {
                            $v = $meta + $v;
                        }
                    }
                    unset($v);
                    console_flash('ok', 'Vidéo mise à jour.');
                }
                central_videos_save($list);
                console_go('videos');

            case 'video_toggle':
            case 'video_delete':
                $list = central_videos();
                foreach ($list as $i => $v) {
                    if ($v['uid'] === ($_POST['uid'] ?? '')) {
                        if ($action === 'video_delete') {
                            @unlink(central_videos_dir() . '/' . basename((string)$v['file']));
                            unset($list[$i]);
                        } else {
                            $list[$i]['published'] = empty($v['published']);
                        }
                    }
                }
                central_videos_save($list);
                console_flash('ok', $action === 'video_delete' ? 'Vidéo supprimée de tous les clients.' : 'Visibilité de la vidéo modifiée.');
                console_go('videos');

            // ----------------------------------------------------- Licences et abonnements
            case 'licence_save':
                if (!isset(instances_registry()[$slug])) {
                    throw new RuntimeException('Client inconnu.');
                }
                $num = fn($k) => trim((string)($_POST[$k] ?? '')) === '' ? null : max(0, (float)str_replace(',', '.', (string)$_POST[$k]));
                $until = trim((string)($_POST['paid_until'] ?? ''));
                if ($until !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $until)) {
                    throw new RuntimeException('Échéance invalide.');
                }
                $email = mb_strtolower(trim((string)($_POST['contact_email'] ?? '')));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('E-mail de facturation invalide.');
                }
                platform_licence_save($slug, ['plan' => mb_substr(trim((string)($_POST['plan'] ?? '')), 0, 60) ?: 'Abonnement', 'price_base' => $num('price_base'),
                    'price_ai' => $num('price_ai'), 'ai' => !empty($_POST['ai']), 'paid_until' => $until ?: null, 'status' => !empty($_POST['suspended']) ? 'suspended' : 'active',
                    'note' => mb_substr(trim((string)($_POST['note'] ?? '')), 0, 300), 'contact_email' => $email]);
                console_flash('ok', 'Licence de « ' . instances_registry()[$slug]['name'] . ' » enregistrée : appliquée immédiatement dans son espace.');
                console_go((string)($_POST['back'] ?? '') === 'billing' ? 'billing' : '', ['edit' => $slug]);

            case 'licence_extend':
                $until = platform_licence_extend($slug, (int)($_POST['months'] ?? 1), $me['name']);
                console_flash('ok', 'Paiement enregistré : licence de « ' . instances_registry()[$slug]['name'] . ' » prolongée jusqu\'au ' . date('d/m/Y', strtotime($until)) . '.');
                console_go((string)($_POST['back'] ?? '') === 'billing' ? 'billing' : '');

            case 'billing_settings':
                $num = fn($k, $max) => max(0, min($max, (float)str_replace(',', '.', (string)($_POST[$k] ?? 0))));
                $email = trim((string)($_POST['operator_email'] ?? ''));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('E-mail des alertes invalide.');
                }
                $sk = trim((string)($_POST['stripe_secret_key'] ?? ''));
                $wh = trim((string)($_POST['stripe_webhook_secret'] ?? ''));
                if ($sk !== '' && !preg_match('/^(sk|rk)_(test|live)_\w+$/', $sk)) {
                    throw new RuntimeException('Clé secrète Stripe invalide (sk_live_… ou sk_test_…).');
                }
                if ($wh !== '' && !str_starts_with($wh, 'whsec_')) {
                    throw new RuntimeException('Secret du webhook invalide (whsec_…).');
                }
                platform_settings_save(['operator_name' => trim((string)($_POST['operator_name'] ?? '')) ?: 'NLapps', 'operator_email' => $email,
                    'price_base' => $num('price_base', 100000), 'price_ai' => $num('price_ai', 100000), 'vat' => $num('vat', 30), 'grace_days' => (int)$num('grace_days', 60)]
                    + ($sk !== '' ? ['stripe_secret_key' => $sk] : []) + ($wh !== '' ? ['stripe_webhook_secret' => $wh] : [])
                    + (!empty($_POST['stripe_remove']) ? ['stripe_secret_key' => '', 'stripe_webhook_secret' => ''] : []));
                console_flash('ok', 'Réglages des abonnements enregistrés.');
                console_go('billing');

            case 'stripe_test':
                $b = platform_stripe('GET', '/v1/balance');
                console_flash('ok', 'Stripe répond (' . (platform_stripe_test_mode() ? 'mode test' : 'mode réel') . ', ' . count((array)($b['available'] ?? [])) . ' solde(s)).');
                console_go('billing');

            // ----------------------------------------------------- Versions
            case 'release_notes':
                platform_release_record(['version' => (string)($_POST['version'] ?? ''), 'notes' => trim((string)($_POST['notes'] ?? ''))]);
                console_flash('ok', 'Notes de la version ' . ($_POST['version'] ?? '') . ' enregistrées.');
                console_go('versions');

            // ----------------------------------------------------- FAQ
            case 'faq_save':
                $q = mb_substr(trim((string)($_POST['question'] ?? '')), 0, 200);
                $a = mb_substr(trim((string)($_POST['answer'] ?? '')), 0, 3000);
                if ($q === '' || $a === '') {
                    throw new RuntimeException('Question et réponse obligatoires.');
                }
                platform_faq_save(['id' => (int)($_POST['id'] ?? 0) ?: null, 'question' => $q, 'answer' => $a, 'keywords' => mb_substr(trim((string)($_POST['keywords'] ?? '')), 0, 400),
                    'link_label' => mb_substr(trim((string)($_POST['link_label'] ?? '')), 0, 60), 'link_route' => preg_replace('/[^a-z0-9\/_=&?-]/i', '', (string)($_POST['link_route'] ?? '')),
                    'admin_only' => !empty($_POST['admin_only']), 'active' => !empty($_POST['active']), 'position' => (int)($_POST['position'] ?? 0), 'updated_at' => date('Y-m-d H:i:s')]);
                console_flash('ok', 'Question enregistrée : le chatbot de tous les clients la propose aussitôt.');
                console_go('faq');

            case 'faq_delete':
                platform_faq_delete((int)($_POST['id'] ?? 0));
                console_flash('ok', 'Question supprimée.');
                console_go('faq');

            // ----------------------------------------------------- Centre d'assistance
            case 'hub_link':
                $key = trim((string)($_POST['hub_console_key'] ?? '')) ?: (string)platform_setting('hub_console_key');
                $r = platform_hub_link((string)($_POST['hub_url'] ?? ''), $key);
                console_flash('ok', 'Console reliée au centre d\'assistance' . (!empty($r['hub_version']) ? ' (version ' . $r['hub_version'] . ')' : '') . ' : il ne garde plus que les conversations.');
                console_go('assistance');

            case 'hub_import':
                $rep = platform_hub_import(!empty($_POST['packages']));
                $_SESSION['import_report'] = $rep;
                console_flash($rep['errors'] ? 'error' : 'ok', 'Reprise terminée : ' . $rep['releases'] . ' version(s), ' . $rep['videos'] . ' vidéo(s), ' . $rep['faq'] . ' question(s), '
                    . count($rep['clients']) . ' client(s), ' . $rep['events'] . ' paiement(s)' . ($rep['errors'] ? ' — ' . count($rep['errors']) . ' anomalie(s), voir le détail.' : '.'));
                console_go('assistance');

            case 'hub_provision':
                $res = platform_hub_provision($slug, !empty($_POST['new_key']));
                console_flash('ok', '« ' . instances_registry()[$slug]['name'] . ' » : ' . $res . ' (conversation en direct avec l\'assistance).');
                console_go((string)($_POST['back'] ?? '') === 'assistance' ? 'assistance' : '');
        }
    } catch (Throwable $e) {
        console_flash('error', $e->getMessage());
        $_SESSION['form'] = array_diff_key($_POST, array_flip(['admin_password', 'db_pass', 'password', 'current', 'new', 'confirm', '_token']));
        $back = match (true) {
            $action === 'create' => 'new', $action === 'adopt' => 'adopt',
            in_array($action, ['password', 'totp_enable', 'totp_disable'], true) => 'account',
            str_starts_with($action, 'admin_') => 'admins', str_starts_with($action, 'video_') => 'videos', str_starts_with($action, 'faq_') => 'faq',
            str_starts_with($action, 'hub_') => 'assistance', $action === 'release_notes' || $action === 'update' => 'versions',
            in_array($action, ['billing_settings', 'stripe_test'], true) || ($_POST['back'] ?? '') === 'billing' => 'billing', default => '',
        };
        console_go($back, $action === 'hosts' ? ['edit' => $slug] : []);
    }
}

// Migration automatique après une mise à jour du code
if ($logged && $page === 'migrate') {
    platform_release_current();
    $res = instances_migrate_all();
    $ko = array_filter($res, fn($r) => $r !== 'ok');
    console_flash($ko ? 'error' : 'ok', $ko ? 'Migration en échec : ' . implode(' · ', array_map(fn($k, $v) => "$k : $v", array_keys($ko), $ko)) : plural(count($res), 'base migrée', 'bases migrées') . ' en version ' . APP_VERSION . '.');
    console_go();
}

// Paquet d'une version de l'historique
if ($logged && $page === 'versions' && isset($_GET['dl'])) {
    foreach (platform_releases() as $r) {
        $path = !empty($r['file']) ? platform_releases_dir() . '/' . basename((string)$r['file']) : '';
        if ($r['version'] === $_GET['dl'] && $path !== '' && is_file($path)) {
            header('Content-Type: application/zip');
            header('Content-Length: ' . filesize($path));
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            readfile($path);
            exit;
        }
    }
    console_flash('error', 'Paquet introuvable pour cette version.');
    console_go('versions');
}
if ($page === 'update') {
    $page = 'versions'; // ancienne adresse
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
$form = $_SESSION['form'] ?? [];
unset($_SESSION['form']);
$f = fn(string $k, string $d = '') => e($form[$k] ?? $d);
$registry = $logged ? instances_registry() : [];
$single = is_file(ROOT . '/config.php');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Console NLapps · Centriva</title>
<style>
:root { --bg:#f4f6fb; --card:#fff; --text:#1e1b4b; --muted:#64748b; --border:#e2e8f0; --primary:#4f46e5; --soft:#eef2ff; --green:#059669; --red:#dc2626; --amber:#d97706; }
@media (prefers-color-scheme: dark) { :root { --bg:#0e1122; --card:#171b33; --text:#e2e8f0; --muted:#94a3b8; --border:#2a3055; --soft:#22285a; } }
* { box-sizing: border-box; }
body { margin:0; font-family: Inter, system-ui, -apple-system, sans-serif; background:var(--bg); color:var(--text); line-height:1.45; }
header { background:linear-gradient(135deg,#312e81,#4f46e5); color:#fff; padding:1rem 1.25rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
header b { font-size:1.1rem; } header nav { display:flex; gap:.25rem; flex-wrap:wrap; margin-left:auto; }
header nav a { color:#fff; text-decoration:none; padding:.4rem .75rem; border-radius:8px; font-size:.92rem; } header nav a.on, header nav a:hover { background:rgba(255,255,255,.18); }
main { max-width:1100px; margin:0 auto; padding:1.25rem 1rem 3rem; }
h1 { font-size:1.45rem; margin:.5rem 0 1rem; } h2 { font-size:1.1rem; margin:0 0 .75rem; }
.card { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:1.1rem 1.2rem; margin-bottom:1rem; box-shadow:0 2px 8px rgba(30,27,75,.05); }
.muted { color:var(--muted); } small { font-size:.85rem; }
label { display:block; font-weight:600; font-size:.88rem; margin:.7rem 0 .25rem; }
input, select, textarea { width:100%; padding:.6rem .7rem; border:1px solid var(--border); border-radius:9px; font:inherit; background:var(--card); color:var(--text); }
.check { display:flex; gap:.5rem; align-items:center; font-weight:500; } .check input { width:auto; }
.grid2 { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 1rem; }
@media (max-width:640px) { .grid2 { grid-template-columns:1fr; } }
.btn { display:inline-flex; align-items:center; gap:.35rem; padding:.55rem 1rem; border-radius:9px; border:1px solid var(--border); background:var(--card); color:var(--text); font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
.btn.primary { background:var(--primary); border-color:var(--primary); color:#fff; } .btn.danger { color:var(--red); } .btn.sm { padding:.35rem .7rem; font-size:.85rem; }
.flash { padding:.75rem 1rem; border-radius:10px; margin-bottom:1rem; } .flash.ok { background:#ecfdf5; color:#065f46; } .flash.error { background:#fef2f2; color:#991b1b; }
@media (prefers-color-scheme: dark) { .flash.ok { background:#064e3b; color:#d1fae5; } .flash.error { background:#7f1d1d; color:#fee2e2; } }
.tag { display:inline-block; padding:.12rem .55rem; border-radius:999px; font-size:.75rem; font-weight:700; background:var(--soft); color:var(--primary); }
.tag.green { background:#d1fae5; color:var(--green); } .tag.red { background:#fee2e2; color:var(--red); } .tag.amber { background:#fef3c7; color:var(--amber); }
.clients { display:grid; grid-template-columns:repeat(auto-fill,minmax(320px,1fr)); gap:1rem; }
.client h2 { display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; margin-bottom:.25rem; }
.kpis { display:grid; grid-template-columns:repeat(4,1fr); gap:.5rem; margin:.75rem 0; text-align:center; }
.kpis div { background:var(--soft); border-radius:10px; padding:.45rem .2rem; } .kpis b { display:block; font-size:1.15rem; } .kpis span { font-size:.72rem; color:var(--muted); }
.row { display:flex; gap:.5rem; flex-wrap:wrap; align-items:center; } .row form { margin:0; }
details summary { cursor:pointer; font-weight:600; font-size:.9rem; margin-top:.5rem; }
code { background:var(--soft); padding:.1rem .35rem; border-radius:6px; font-size:.85rem; word-break:break-all; }
.auth { max-width:420px; margin:3rem auto; }
.licence { margin-top:.6rem; padding:.55rem .7rem; border-radius:10px; background:var(--soft); }
table.list { width:100%; border-collapse:collapse; font-size:.92rem; } table.list th { text-align:left; font-size:.78rem; color:var(--muted); font-weight:600; padding:.4rem .5rem; border-bottom:1px solid var(--border); }
table.list td { padding:.55rem .5rem; border-bottom:1px solid var(--border); vertical-align:top; } .scroll { overflow-x:auto; }
.stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:.75rem; margin-bottom:1rem; } .stats .card { margin:0; } .stats b { display:block; font-size:1.4rem; }
pre.notes { white-space:pre-wrap; font:inherit; font-size:.9rem; margin:.4rem 0 0; color:var(--text); }
</style>
</head>
<body>
<header>
  <b>Centriva · Administration de la plateforme</b><small style="opacity:.8">v<?= e(APP_VERSION) ?></small>
  <?php if ($logged): ?>
  <nav>
    <a class="<?= in_array($page, ['', 'new', 'adopt'], true) ? 'on' : '' ?>" href="console.php">Clients</a>
    <a class="<?= $page === 'billing' ? 'on' : '' ?>" href="console.php?p=billing">Abonnements</a>
    <a class="<?= $page === 'versions' ? 'on' : '' ?>" href="console.php?p=versions">Versions</a>
    <a class="<?= $page === 'videos' ? 'on' : '' ?>" href="console.php?p=videos">Vidéos</a>
    <a class="<?= $page === 'faq' ? 'on' : '' ?>" href="console.php?p=faq">FAQ</a>
    <a class="<?= $page === 'assistance' ? 'on' : '' ?>" href="console.php?p=assistance">Assistance</a>
    <a class="<?= $page === 'admins' ? 'on' : '' ?>" href="console.php?p=admins">Super administrateurs</a>
    <a class="<?= $page === 'account' ? 'on' : '' ?>" href="console.php?p=account" title="Mon compte"><?= e($me['name']) ?></a>
    <a href="console.php?p=logout">Déconnexion</a>
  </nav>
  <?php endif; ?>
</header>
<main>
<?php foreach ($flash as [$t, $m]): ?><div class="flash <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>

<?php if ($page === 'setup'): $hasData = is_file(ROOT . '/config.php') && !instances_registry(); ?>
  <div class="card auth" style="max-width:520px">
    <h1>Mise en service de la plateforme</h1>
    <p class="muted">Créez le premier compte <b>super administrateur</b> : il crée les clients, installe les mises à jour et publie les vidéos pour tous.</p>
    <form method="post"><?= csrf_field() ?>
      <?php if ($legacy): ?>
        <label>Mot de passe de l'ancienne console</label><input type="password" name="code" required autocomplete="off" autofocus>
      <?php else: ?>
        <p class="muted"><small>Pour prouver que vous gérez ce serveur, recopiez le code du fichier <code>storage/console-code.txt</code> (FTP ou gestionnaire de fichiers de l'hébergeur).</small></p>
        <label>Code d'installation</label><input name="code" required autocomplete="off" autofocus>
      <?php endif; ?>
      <div class="grid2">
        <div><label>Votre nom</label><input name="name" required value="<?= $f('name') ?>"></div>
        <div><label>E-mail (identifiant)</label><input type="email" name="email" required value="<?= $f('email') ?>"></div>
        <div><label>Mot de passe <small class="muted">(12 caractères min.)</small></label><input type="password" name="password" minlength="12" required autocomplete="new-password"></div>
        <div><label>Confirmation</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password"></div>
      </div>
      <?php if ($hasData): ?>
        <div class="card" style="background:var(--soft);margin-top:1rem">
          <label class="check" style="margin-top:0"><input type="checkbox" name="adopt" value="1" checked> Les données actuelles (comptes, catalogue, commandes…) deviennent le premier client</label>
          <div class="grid2"><div><label>Nom du client</label><input name="adopt_name" value="IMSS"></div><div><label>Identifiant</label><input name="adopt_slug" value="imss" pattern="[a-z0-9][a-z0-9\-]{0,38}[a-z0-9]?"></div></div>
          <p class="muted" style="margin:.4rem 0 0"><small>Sa base n'est pas modifiée. Ses utilisateurs se connecteront sur la page d'accueil avec leur e-mail habituel.</small></p>
        </div>
      <?php endif; ?>
      <p><button class="btn primary">Créer le compte et démarrer</button></p>
    </form>
  </div>

<?php elseif ($page === 'login' || $page === 'totp'): ?>
  <div class="card auth">
    <h1>Administration Centriva</h1>
    <?php if ($page === 'totp'): ?>
      <form method="post" action="console.php"><?= csrf_field() ?>
        <label>Code de l'application d'authentification</label><input name="totp" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus>
        <p><button class="btn primary">Valider</button></p></form>
    <?php else: ?>
      <form method="post" action="console.php"><?= csrf_field() ?>
        <label>E-mail</label><input type="email" name="email" required autofocus autocomplete="username">
        <label>Mot de passe</label><input type="password" name="password" required autocomplete="current-password">
        <p><button class="btn primary">Se connecter</button></p></form>
    <?php endif; ?>
  </div>

<?php elseif ($page === 'new'): ?>
  <h1>Nouveau client</h1>
  <form method="post" class="card" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <h2>L'espace</h2>
    <div class="grid2">
      <div><label>Nom du client</label><input name="name" value="<?= $f('name') ?>" required placeholder="ex : Groupe Santé Var"></div>
      <div><label>Identifiant <small class="muted">(minuscules, chiffres, tirets — définitif)</small></label><input name="slug" value="<?= $f('slug') ?>" required pattern="[a-z0-9][a-z0-9\-]{0,38}[a-z0-9]?" placeholder="ex : sante-var"></div>
    </div>
    <p class="muted" style="margin:.6rem 0 0">L'espace sera accessible à <b><?= e(preg_replace('#^https?://#', '', console_space_url('identifiant', []))) ?></b> (l'identifiant choisi ci-dessus). Les données de chaque espace sont séparées : base, fichiers, comptes et sessions.</p>
    <details style="margin-top:.4rem"><summary class="muted">Adresse dédiée (facultatif)</summary>
      <label>Adresse(s) propre(s) au client <small class="muted">(une par ligne ; à faire pointer vers ce dossier chez l'hébergeur)</small></label>
      <textarea name="hosts" rows="2" placeholder="achats.groupe-sante-var.fr"><?= $f('hosts') ?></textarea></details>
    <label>Nom affiché de l'application</label><input name="app_name" value="<?= $f('app_name', 'Centriva') ?>">

    <h2 style="margin-top:1.4rem">Base de données</h2>
    <label class="check"><input type="radio" name="db_driver" value="sqlite" <?= ($form['db_driver'] ?? 'sqlite') === 'sqlite' ? 'checked' : '' ?>> SQLite : un fichier propre au client, rien à créer chez l'hébergeur (jusqu'à quelques dizaines d'utilisateurs)</label>
    <label class="check"><input type="radio" name="db_driver" value="mysql" <?= ($form['db_driver'] ?? '') === 'mysql' ? 'checked' : '' ?>> MySQL / MariaDB : une base vide, créée pour ce client chez l'hébergeur (recommandé au-delà)</label>
    <div class="grid2">
      <div><label>Serveur</label><input name="db_host" value="<?= $f('db_host', 'localhost') ?>"></div>
      <div><label>Port</label><input name="db_port" value="<?= $f('db_port', '3306') ?>"></div>
      <div><label>Nom de la base</label><input name="db_name" value="<?= $f('db_name') ?>"></div>
      <div><label>Utilisateur</label><input name="db_user" value="<?= $f('db_user') ?>"></div>
      <div><label>Mot de passe</label><input type="password" name="db_pass" autocomplete="new-password"></div>
    </div>

    <h2 style="margin-top:1.4rem">Premier administrateur du client</h2>
    <div class="grid2">
      <div><label>Prénom</label><input name="admin_first_name" value="<?= $f('admin_first_name') ?>" required></div>
      <div><label>Nom</label><input name="admin_last_name" value="<?= $f('admin_last_name') ?>" required></div>
      <div><label>E-mail (identifiant)</label><input type="email" name="admin_email" value="<?= $f('admin_email') ?>" required></div>
      <div><label>Mot de passe provisoire <small class="muted">(10 caractères min., à transmettre au client)</small></label><input name="admin_password" minlength="10" required value="<?= e(substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 12)) ?>"></div>
    </div>

    <h2 style="margin-top:1.4rem">Licence</h2>
    <p class="muted" style="margin:0"><small>Abonnement mensuel au tarif de la plateforme (<?= e(number_format((float)platform_setting('price_base'), 2, ',', ' ')) ?> € HT ; réglable ensuite sur la fiche du client), sans échéance tant qu'aucune n'est fixée. <?= platform_hub_linked() ? 'L\'accès à la conversation en direct avec l\'assistance est créé automatiquement.' : 'Reliez le centre d\'assistance (menu Assistance) pour activer la conversation en direct.' ?></small></p>
    <label class="check"><input type="checkbox" name="ai" value="1" <?= !empty($form['ai']) ? 'checked' : '' ?>> Option assistant IA (+<?= e(number_format((float)platform_setting('price_ai'), 2, ',', ' ')) ?> € HT / mois)</label>
    <label class="check" style="margin-top:1rem"><input type="checkbox" name="demo" value="1" <?= !empty($form['demo']) ? 'checked' : '' ?>> Remplir avec les données de démonstration (centres, fournisseurs, articles et comptes fictifs)</label>
    <label class="check"><input type="checkbox" name="public_demo" value="1" <?= !empty($form['public_demo']) ? 'checked' : '' ?>> Démo publique pour vos prospects : connexion en un clic avec chaque rôle, données remises à zéro chaque nuit, paramètres et e-mails désactivés</label>
    <p><button class="btn primary">Créer l'espace</button></p>
  </form>

<?php elseif ($page === 'adopt' && $single): ?>
  <h1>Reprendre l'installation actuelle</h1>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="adopt">
    <p class="muted">L'installation Centriva existante (config.php, base, fichiers) devient un client de la console. <strong>Sa base n'est pas modifiée</strong> : comptes, commandes, réglages et licence sont conservés. Ses fichiers sont copiés dans son espace ; l'ancienne configuration est mise de côté dans <code>storage/</code>.</p>
    <div class="grid2">
      <div><label>Nom du client</label><input name="name" value="<?= $f('name', (string)((require ROOT . '/config.php')['app_name'] ?? '')) ?>" required></div>
      <div><label>Identifiant</label><input name="slug" value="<?= $f('slug') ?>" required placeholder="ex : imss"></div>
    </div>
    <p class="muted" style="margin:.6rem 0 0">L'espace sera accessible à <b><?= e(preg_replace('#^https?://#', '', console_space_url('identifiant', []))) ?></b>. Si les utilisateurs se connectent aujourd'hui à une autre adresse, indiquez-la ci-dessous pour qu'elle continue de fonctionner.</p>
    <label>Adresse(s) actuelle(s) de l'installation <small class="muted">(facultatif, une par ligne)</small></label>
    <textarea name="hosts" rows="2"><?= $f('hosts') ?></textarea>
    <p><button class="btn primary">Reprendre comme client</button></p>
  </form>

<?php elseif (in_array($page, ['versions', 'billing', 'faq', 'assistance'], true)): ?>
  <?php require APP . '/views/console/' . $page . '.php'; ?>

<?php elseif ($page === 'account'): ?>
  <h1>Mon compte</h1>
  <div class="grid2">
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <h2>Mot de passe</h2>
      <label>Mot de passe actuel</label><input type="password" name="current" required autocomplete="current-password">
      <label>Nouveau mot de passe <small class="muted">(12 caractères minimum)</small></label><input type="password" name="new" minlength="12" required autocomplete="new-password">
      <label>Confirmation</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password">
      <p><button class="btn primary">Changer le mot de passe</button></p>
    </form>
    <div class="card">
      <h2>Double authentification <?= !empty($me['totp']) ? '<span class="tag green">activée</span>' : '<span class="tag amber">conseillée</span>' ?></h2>
      <?php if (!empty($me['totp'])): ?>
        <p class="muted">Un code de votre application d'authentification est demandé à chaque connexion.</p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="totp_disable"><label>Mot de passe (pour désactiver)</label><input type="password" name="current" required><p><button class="btn danger">Désactiver</button></p></form>
      <?php else: $sec = $_SESSION['totp_setup'] ??= base32_encode(random_bytes(20)); ?>
        <p class="muted">Ce compte donne accès à tous les clients : protégez-le. Scannez ce QR code avec Google Authenticator, Microsoft Authenticator ou Authy (ou saisissez la clé <code><?= e(trim(chunk_split($sec, 4, ' '))) ?></code>), puis saisissez le code affiché.</p>
        <div data-qr="<?= e(totp_uri($sec, $me['email'])) ?>" style="width:180px;height:180px;background:#fff;padding:6px;border-radius:8px"></div>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="totp_enable"><label>Code à 6 chiffres</label><input name="code" inputmode="numeric" maxlength="6" required><p><button class="btn primary">Activer</button></p></form>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>const q = document.querySelector('[data-qr]'); if (window.QRCode && q) new QRCode(q, { text: q.dataset.qr, width: 168, height: 168 });</script>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($page === 'admins'): ?>
  <h1>Super administrateurs</h1>
  <div class="card">
    <?php foreach (superadmins() as $a): ?>
      <div class="row" style="justify-content:space-between;padding:.5rem 0;border-bottom:1px solid var(--border)">
        <div><b><?= e($a['name']) ?></b> <small class="muted"><?= e($a['email']) ?></small> <?= !empty($a['totp']) ? '<span class="tag green">double authentification</span>' : '<span class="tag amber">sans double authentification</span>' ?><br>
          <small class="muted">Dernière connexion : <?= !empty($a['last_login']) ? e(date('d/m/Y H:i', strtotime($a['last_login']))) : 'jamais' ?></small></div>
        <?php if ($a['id'] !== $me['id']): ?><form method="post" onsubmit="return confirm('Supprimer ce compte ?')"><?= csrf_field() ?><input type="hidden" name="action" value="admin_delete"><input type="hidden" name="id" value="<?= e($a['id']) ?>"><button class="btn sm danger">Supprimer</button></form><?php else: ?><small class="muted">vous</small><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <form method="post" class="card" style="max-width:560px" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="action" value="admin_add">
    <h2>Ajouter un super administrateur</h2>
    <div class="grid2"><div><label>Nom</label><input name="name" required></div><div><label>E-mail</label><input type="email" name="email" required></div></div>
    <label>Mot de passe provisoire <small class="muted">(12 caractères min., à transmettre ; il pourra le changer dans « Mon compte »)</small></label><input name="password" minlength="12" required value="<?= e(substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 14)) ?>">
    <p><button class="btn primary">Créer le compte</button></p>
  </form>

<?php elseif ($page === 'videos'): $cv = central_videos(); ?>
  <h1>Vidéos communes à tous les clients</h1>
  <p class="muted">Publiées une fois ici, elles apparaissent dans « Tutoriels vidéo » de chaque client et l'aide en ligne les propose au bon chapitre. La vidéo marquée « accueil » s'ouvre à la première connexion des salariés.</p>
  <form method="post" enctype="multipart/form-data" class="card" data-video-upload><?= csrf_field() ?><input type="hidden" name="action" value="video_upload"><input type="hidden" name="duration" value="">
    <h2>Publier une vidéo</h2>
    <label>Fichier MP4 (H.264)</label><input type="file" name="video" accept="video/mp4,.mp4,.m4v" required>
    <small class="muted">Limite du serveur : <?= e((string)ini_get('upload_max_filesize')) ?>.</small>
    <div class="grid2">
      <div><label>Titre</label><input name="title" required maxlength="150"></div>
      <div><label>Mots-clés <small class="muted">(aident le chatbot)</small></label><input name="keywords" maxlength="400"></div>
      <div><label>Visible par</label><select name="audience"><option value="all">Tous les utilisateurs</option><option value="admin">Administrateurs seulement</option></select></div>
      <div><label>Ordre</label><input type="number" name="position" value="<?= count($cv) * 10 ?>"></div>
    </div>
    <label>Description</label><textarea name="description" rows="2"></textarea>
    <label>Chapitres <small class="muted">(un par ligne : 4:12 Titre | mots-clés)</small></label><textarea name="chapters" rows="4"></textarea>
    <label class="check"><input type="checkbox" name="welcome" value="1"> Vidéo d'accueil des salariés</label>
    <label class="check"><input type="checkbox" name="publish" value="1" checked> Publier tout de suite</label>
    <p><button class="btn primary">Envoyer la vidéo</button></p>
  </form>
  <?php foreach ($cv as $v): ?>
    <div class="card" style="<?= empty($v['published']) ? 'opacity:.65' : '' ?>">
      <div class="row" style="justify-content:space-between"><div><b><?= e($v['title']) ?></b> <?= !empty($v['published']) ? '<span class="tag green">publiée</span>' : '<span class="tag">brouillon</span>' ?><?= !empty($v['welcome']) ? ' <span class="tag">accueil</span>' : '' ?><?= ($v['audience'] ?? '') === 'admin' ? ' <span class="tag amber">administrateurs</span>' : '' ?><br>
        <small class="muted"><?= !empty($v['duration']) ? e(video_time((int)$v['duration'])) . ' · ' : '' ?><?= round(((int)($v['size'] ?? 0)) / 1048576, 1) ?> Mo · <?= count($v['chapters'] ?? []) ?> chapitre(s)</small></div>
        <div class="row">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="video_toggle"><input type="hidden" name="uid" value="<?= e($v['uid']) ?>"><button class="btn sm"><?= !empty($v['published']) ? 'Retirer' : 'Publier' ?></button></form>
          <form method="post" onsubmit="return confirm('Supprimer cette vidéo pour tous les clients ?')"><?= csrf_field() ?><input type="hidden" name="action" value="video_delete"><input type="hidden" name="uid" value="<?= e($v['uid']) ?>"><button class="btn sm danger">Supprimer</button></form>
        </div></div>
      <details><summary>Modifier</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="video_save"><input type="hidden" name="uid" value="<?= e($v['uid']) ?>">
          <div class="grid2"><div><label>Titre</label><input name="title" value="<?= e($v['title']) ?>"></div><div><label>Mots-clés</label><input name="keywords" value="<?= e($v['keywords'] ?? '') ?>"></div>
            <div><label>Visible par</label><select name="audience"><option value="all">Tous les utilisateurs</option><option value="admin" <?= ($v['audience'] ?? '') === 'admin' ? 'selected' : '' ?>>Administrateurs seulement</option></select></div>
            <div><label>Ordre</label><input type="number" name="position" value="<?= (int)($v['position'] ?? 0) ?>"></div></div>
          <label>Description</label><textarea name="description" rows="2"><?= e($v['description'] ?? '') ?></textarea>
          <label>Chapitres</label><textarea name="chapters" rows="5"><?= e(video_chapters_text($v['chapters'] ?? [])) ?></textarea>
          <label class="check"><input type="checkbox" name="welcome" value="1" <?= !empty($v['welcome']) ? 'checked' : '' ?>> Vidéo d'accueil des salariés</label>
          <p><button class="btn sm primary">Enregistrer</button></p></form>
      </details>
    </div>
  <?php endforeach; ?>
  <?php if (!$cv): ?><div class="card muted">Aucune vidéo commune pour l'instant.</div><?php endif; ?>
  <script>
  document.querySelector('[data-video-upload] input[type=file]')?.addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return; const form = e.target.form, v = document.createElement('video'); v.preload = 'metadata';
    v.onloadedmetadata = () => { form.duration.value = Math.round(v.duration || 0); URL.revokeObjectURL(v.src); }; v.src = URL.createObjectURL(f);
    if (!form.title.value) form.title.value = f.name.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ');
  });
  </script>

<?php else: ?>
  <div class="row" style="justify-content:space-between">
    <h1><?= plural(count($registry), 'client', 'clients') ?></h1>
    <div class="row"><?php if ($single): ?><a class="btn" href="console.php?p=adopt">Reprendre l'installation actuelle</a><?php endif; ?><a class="btn primary" href="console.php?p=new">+ Nouveau client</a></div>
  </div>
  <?php if (!$registry): ?>
    <div class="card"><p>Aucun client pour l'instant.</p>
      <p class="muted"><small>Chaque client aura sa base, ses fichiers, ses comptes et sa licence. Tous se connectent à la même adresse, suivie de l'identifiant de leur espace (ex. <code><?= e(preg_replace('#^https?://#', '', console_space_url('imss', []))) ?></code>) ; une adresse dédiée reste possible.<?= $single ? ' L\'installation actuelle peut devenir le premier client sans rien perdre.' : '' ?></small></p></div>
  <?php endif; ?>
  <div class="clients">
  <?php foreach ($registry as $slug => $i): $st = instance_stats($slug); $edit = ($_GET['edit'] ?? '') === $slug; ?>
    <div class="card client">
      <h2><?= e($i['name']) ?> <?= !empty($i['suspended']) ? '<span class="tag red">suspendu</span>' : '<span class="tag green">actif</span>' ?><?= !empty($i['public_demo']) ? ' <span class="tag amber">démo publique</span>' : (!empty($i['demo']) ? ' <span class="tag amber">démo</span>' : '') ?></h2>
      <small class="muted"><?= e($slug) ?> · <?= e($st['driver'] ?? '?') ?> · base v<?= e($st['db_version'] ?? '?') ?><?= ($st['db_version'] ?? '') !== APP_VERSION ? ' <span class="tag amber">à migrer</span>' : '' ?></small>
      <div class="row" style="margin-top:.35rem"><?php $pathUrl = console_space_url($slug, []); ?><a href="<?= e($pathUrl) ?>" target="_blank" rel="noopener"><small><?= e(preg_replace('#^https?://#', '', $pathUrl)) ?></small></a><?php foreach ((array)$i['hosts'] as $h): ?><a href="https://<?= e($h) ?>/" target="_blank" rel="noopener"><small><?= e($h) ?></small></a><?php endforeach; ?></div>
      <?php if ($st['ok']): ?>
        <div class="kpis">
          <div><b><?= (int)$st['users'] ?></b><span>comptes</span></div>
          <div><b><?= (int)$st['centers'] ?></b><span>centres</span></div>
          <div><b><?= (int)$st['products'] ?></b><span>articles</span></div>
          <div><b><?= (int)$st['orders_month'] ?></b><span>bons ce mois</span></div>
        </div>
        <?php if (!empty($i['public_demo'])): ?><small class="muted">Dernière remise à zéro : <?= !empty($st['demo_reset_at']) ? e(date('d/m/Y H:i', strtotime((string)$st['demo_reset_at']))) : '—' ?></small><br><?php endif; ?>
        <small class="muted">Dernière connexion : <?= $st['last_login'] ? e(date('d/m/Y H:i', strtotime((string)$st['last_login']))) : 'jamais' ?></small>
      <?php else: ?><p class="flash error"><small><?= e($st['error']) ?></small></p><?php endif; ?>
      <?php $lic = platform_licence($slug); $lr = platform_licence_row($slug); ?>
      <div class="licence">
        <div><?= console_licence_tag($lic['status']) ?> <b><?= e($lic['plan']) ?></b><?= $lic['ai'] ? ' <span class="tag">IA</span>' : '' ?>
          <small class="muted"> · <?= e(number_format(platform_monthly_ttc($slug) / 100, 2, ',', ' ')) ?> € TTC / mois</small></div>
        <small class="muted"><?= !empty($i['demo']) ? 'Espace de démonstration : licence permanente' : ($lic['paid_until'] ? 'Payé jusqu\'au ' . e(date('d/m/Y', strtotime($lic['paid_until']))) . ($lic['days_left'] !== null && $lic['days_left'] >= 0 ? ' (J-' . $lic['days_left'] . ')' : '') : 'Sans échéance') ?>
          · <?= e(platform_billing_active($slug) ? ($lr['billing_method'] === 'sepa_debit' ? 'prélèvement SEPA' : 'paiement automatique') . ($lr['billing_status'] !== 'active' ? ' — ' . platform_billing_status_label($lr['billing_status']) : '') : ($lr['billing_status'] === 'canceled' ? 'abonnement en ligne résilié' : 'paiement manuel')) ?>
          · assistance : <?= $lr['hub_client'] ? 'reliée' : 'non reliée' ?></small>
      </div>
      <div class="row" style="margin-top:.75rem">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="<?= !empty($i['suspended']) ? 'resume' : 'suspend' ?>">
          <button class="btn sm" onclick="return confirm('<?= !empty($i['suspended']) ? 'Rétablir l\\\'accès à cet espace ?' : 'Suspendre cet espace ? Plus personne ne pourra s\\\'y connecter.' ?>')"><?= !empty($i['suspended']) ? 'Rétablir' : 'Suspendre' ?></button></form>
        <?php if (!empty($i['demo'])): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="demo_toggle"><button class="btn sm"><?= !empty($i['public_demo']) ? 'Désactiver la démo publique' : 'Rendre publique' ?></button></form>
        <?php endif; ?>
        <?php if (!empty($i['public_demo'])): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="action" value="demo_reset"><button class="btn sm" onclick="return confirm('Effacer toutes les données de la démo et repartir des données de départ ?')">Remettre à zéro</button></form>
        <?php endif; ?>
      </div>
      <details <?= $edit ? 'open' : '' ?>><summary>Licence et abonnement</summary>
        <?php require APP . '/views/console/licence_form.php'; ?>
      </details>
      <details><summary>Nom et adresse dédiée</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hosts"><input type="hidden" name="slug" value="<?= e($slug) ?>">
          <label>Nom</label><input name="name" value="<?= e($i['name']) ?>">
          <label>Adresses dédiées <small class="muted">(facultatif, une par ligne)</small></label><textarea name="hosts" rows="2"><?= e(implode("\n", (array)$i['hosts'])) ?></textarea>
          <p><button class="btn sm primary">Enregistrer</button></p></form>
      </details>
      <details><summary class="muted">Supprimer l'espace</summary>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slug" value="<?= e($slug) ?>">
          <p class="muted"><small>La base et les fichiers sont d'abord archivés dans <code>storage/clients-supprimes/</code>. Saisissez <b><?= e($slug) ?></b> pour confirmer.</small></p>
          <input name="confirm" autocomplete="off" placeholder="<?= e($slug) ?>"><p><button class="btn sm danger">Supprimer définitivement</button></p></form>
      </details>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</main>
</body>
</html>
