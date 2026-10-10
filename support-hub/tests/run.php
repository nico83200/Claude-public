<?php
declare(strict_types=1);

/** Tests du centre d'assistance (ligne de commande uniquement) : php tests/run.php */
if (PHP_SAPI !== 'cli') {
    exit;
}
$tmp = sys_get_temp_dir() . '/hub-test-' . bin2hex(random_bytes(4));
mkdir($tmp);
putenv('HUB_TEST_DB=' . $tmp . '/hub.sqlite');
require dirname(__DIR__) . '/lib.php';

$ok = 0;
$ko = 0;
function check(bool $cond, string $label): void
{
    global $ok, $ko;
    $cond ? $ok++ : $ko++;
    echo ($cond ? "  \033[32m✔\033[0m " : "  \033[31m✘\033[0m ") . $label . "\n";
}

echo "Accès au chat\n";
$key = hub_create_client('Client test');
$c = hone('SELECT * FROM clients WHERE key_hash = ?', [hash('sha256', $key)]);
check($c && (int)$c['active'] === 1 && str_starts_with($key, 'nlh_'), 'nouveau client : clé créée, accès actif');
check(!function_exists('hub_licence') && !function_exists('hub_stripe') && !function_exists('hub_billing_summary'), 'licences, abonnements et paiements ne sont plus gérés par le centre d\'assistance (4.0)');

echo "Clés\n";
check(hub_client_from_key($key) !== null && hub_client_from_key('nlh_faux') === null, 'clé reconnue, fausse clé refusée');
$new = hub_rotate_key((int)$c['id']);
check(hub_client_from_key($new) !== null && hub_client_from_key($key) !== null, 'renouvellement : nouvelle clé valide, ancienne encore acceptée 14 jours');
hq('UPDATE clients SET prev_key_until = ? WHERE id = ?', [date('Y-m-d H:i:s', strtotime('-1 day')), $c['id']]);
check(hub_client_from_key($key) === null, 'ancienne clé refusée après le délai');

echo "Double authentification\n";
$rfc = base32_encode('12345678901234567890');
check($rfc === 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ' && base32_decode($rfc) === '12345678901234567890', 'base32 (RFC 4648)');
check(totp_code($rfc, 59) === '287082' && totp_code($rfc, 1111111109) === '081804', 'codes TOTP conformes aux vecteurs de la RFC 6238');
$secret = base32_encode(random_bytes(20));
check(totp_verify($secret, totp_code($secret)) && !totp_verify($secret, totp_code($secret)), 'code valide accepté une seule fois (pas de rejeu)');
check(!totp_verify($secret, '000000') || totp_code($secret) === '000000', 'code faux refusé');

echo "Notifications push\n";
$ua = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
$ud = openssl_pkey_get_details($ua)['ec'];
$uaPub = "\x04" . str_pad($ud['x'], 32, "\0", STR_PAD_LEFT) . str_pad($ud['y'], 32, "\0", STR_PAD_LEFT);
$auth = random_bytes(16);
$body = webpush_encrypt('{"title":"Bonjour é"}', b64u($uaPub), b64u($auth));
// Déchiffrement côté « navigateur » (RFC 8291) pour vérifier le message
$salt = substr($body, 0, 16);
$asPub = substr($body, 21, 65);
$cipher = substr($body, 86);
$asPem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $asPub), 64, "\n") . "-----END PUBLIC KEY-----\n";
$shared = openssl_pkey_derive(openssl_pkey_get_public($asPem), $ua, 32);
$ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPub . $asPub, $auth);
$cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
$nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
$plain = openssl_decrypt(substr($cipher, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($cipher, -16));
check(unpack('N', substr($body, 16, 4))[1] === 4096 && ord($body[20]) === 65, 'en-tête aes128gcm (taille d\'enregistrement, clé publique)');
check($plain === "{\"title\":\"Bonjour é\"}\x02", 'message chiffré relu par le destinataire (RFC 8291)');
$v = hub_vapid();
$sig = es256_sign('données', $v['pem']);
$der = fn(string $i) => "\x02" . chr(strlen($i = (ord($i[0]) & 0x80 ? "\0" : '') . ltrim($i, "\0") ?: "\0")) . $i;
$seq = $der(substr($sig, 0, 32)) . $der(substr($sig, 32));
check(strlen($sig) === 64 && openssl_verify('données', "\x30" . chr(strlen($seq)) . $seq, openssl_pkey_get_public(openssl_pkey_get_details(openssl_pkey_get_private($v['pem']))['key']), OPENSSL_ALGO_SHA256) === 1, 'signature VAPID ES256 valide');
check(strlen(b64u_dec($v['public'])) === 65, 'clé publique VAPID au format attendu par les navigateurs');

echo "Disponibilité\n";
hset('schedule', json_encode(['1' => [['09:00', '12:00']]]));
check(hub_in_hours(strtotime('next monday 10:00')) && !hub_in_hours(strtotime('next monday 13:00')) && !hub_in_hours(strtotime('next sunday 10:00')), 'horaires : lundi 10 h ouvert, 13 h et dimanche fermés');
hset('availability_mode', 'manual');
hset('online', '0');
check(!hub_status()['online'], 'mode manuel respecté');

echo "Mise à jour du centre\n";
check(hub_update_allowed('views/inbox.php') && hub_update_allowed('vendor/autoload.php') && !hub_update_allowed('config.php') && !hub_update_allowed('data/x.sqlite') && !hub_update_allowed('../index.php'), 'fichiers remplaçables : code oui, config.php et data/ jamais');
$pk = $tmp . '/maj.zip';
$z = new ZipArchive();
$z->open($pk, ZipArchive::CREATE);
foreach (['VERSION' => '9.9.9', 'lib.php' => '<?php', 'api.php' => '<?php', 'config.php' => 'PIEGE', 'data/hub.sqlite' => 'PIEGE', 'views/inbox.php' => '<?php'] as $n => $c) {
    $z->addFromString('assistance/' . $n, $c);
}
$z->close();
$info = hub_update_inspect($pk);
check($info['version'] === '9.9.9' && in_array('views/inbox.php', $info['files'], true) && !in_array('config.php', $info['files'], true) && !in_array('data/hub.sqlite', $info['files'], true), 'paquet analysé : version lue, config.php et data/ écartés');
$bad = $tmp . '/bad.zip';
$z = new ZipArchive(); $z->open($bad, ZipArchive::CREATE); $z->addFromString('readme.txt', 'x'); $z->close();
try { hub_update_inspect($bad); check(false, 'archive étrangère refusée'); } catch (RuntimeException) { check(true, 'archive étrangère refusée'); }

echo "Pièces jointes\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
check(str_ends_with(hub_store_image($png), '.png'), 'image PNG acceptée');
try {
    hub_store_image('<?php echo 1; ?>');
    check(false, 'fichier non image refusé');
} catch (RuntimeException) {
    check(true, 'fichier non image refusé');
}

// Ancienne vidéo d'une autre application (données d'avant la 4.0, reprises par la console)
hq('INSERT INTO videos (uid, app, title, chapters, audience, file, sha256, size, position, published, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
    ['nl-2', 'autreappli', 'Autre application', '[]', 'all', 'nl-2.mp4', str_repeat('b', 64), 1000, 0, 1, hnow(), hnow()]);

echo "Comptes et applications (3.0)\n";
check((bool)hub_app('centriva'), 'application Centriva créée d\'office');
check(hub_slug('Planning Soins à domicile') === 'planning-soins-a-domicile', 'identifiant technique déduit du nom');
check(hub_valid_username('nicolas') && hub_valid_username('j.martin@nlapps.fr') && !hub_valid_username('ab') && !hub_valid_username('nom avec espace'), 'identifiants valides / refusés');
hq('DELETE FROM users');
$legacy = password_hash('AncienMotDePasse', PASSWORD_DEFAULT);
hset('password_hash', $legacy); hset('totp_secret', 'JBSWY3DPEHPK3PXP');
hub_migrate(hdb());
$adm = hone("SELECT * FROM users WHERE username = 'admin'");
check($adm && $adm['role'] === 'admin' && password_verify('AncienMotDePasse', $adm['password_hash']) && $adm['totp_secret'] === 'JBSWY3DPEHPK3PXP',
    'mise à jour : l\'ancien accès devient le compte « admin » (même mot de passe, même double authentification)');
check(hsetting('legacy_login') === '1' && hsetting('password_hash') === null, 'ancien mot de passe unique retiré, message de connexion activé');
hub_migrate(hdb());
check((int)hone('SELECT COUNT(*) n FROM users')['n'] === 1, 'migration rejouée sans effet (pas de doublon)');
check((bool)hone("SELECT slug FROM apps WHERE slug = 'autreappli'"), 'application déjà utilisée par une vidéo ou un client ajoutée au menu');
check(hone("SELECT id FROM users WHERE username = 'ADMIN'") !== null, 'identifiant insensible à la casse');

echo "Changement de nom : Approvia devient Centriva (3.3)\n";
$old = 'approv' . 'ia';
hq("INSERT OR IGNORE INTO apps (slug, name, color, price_base, price_ai, position, created_at) VALUES (?, 'Approv' || 'ia', '#6366f1', 39, 15, 0, ?)", [$old, hnow()]);
hq('DELETE FROM apps WHERE slug = ?', ['centriva']);
$kOld = hub_create_client('Client historique', '');
hq('UPDATE clients SET app = ? WHERE id = ?', [$old, hub_client_from_key($kOld)['id']]);
hq("INSERT INTO faq (app, question, answer, active, created_at, updated_at) VALUES (?, 'Comment utiliser ' || 'Approv' || 'ia ?', 'Ouvrez ' || 'Approv' || 'ia.', 1, ?, ?)", [$old, hnow(), hnow()]);
hq("DELETE FROM settings WHERE k = 'renamed_centriva'");
hub_migrate(hdb());
$app = hone("SELECT * FROM apps WHERE slug = 'centriva'");
check($app && $app['name'] === 'Centriva' && (float)$app['price_base'] === 39.0 && !hone('SELECT slug FROM apps WHERE slug = ?', [$old]), 'application renommée, tarifs conservés');
check(hub_client_from_key($kOld)['app'] === 'centriva' && !(int)hone('SELECT COUNT(*) n FROM faq WHERE app = ?', [$old])['n'], 'clients et FAQ rattachés à Centriva');
check((bool)hone("SELECT id FROM faq WHERE question = 'Comment utiliser Centriva ?' AND answer = 'Ouvrez Centriva.'"), 'textes de la FAQ renommés');
check(hub_app_slug($old) === 'centriva' && hub_app_slug('autre') === 'autre', 'une installation pas encore mise à jour (« approvia ») est reconnue');
hub_migrate(hdb());
check((int)hone("SELECT COUNT(*) n FROM apps WHERE slug = 'centriva'")['n'] === 1, 'migration rejouée sans effet');

echo "Console de la plateforme Centriva (3.5)\n";
check(!hub_console_key_ok('nlc_x'), 'sans clé de liaison créée : refusé');
hset('console_key_hash', hash('sha256', 'nlc_bonne'));
check(hub_console_key_ok('nlc_bonne') && !hub_console_key_ok('nlc_autre') && !hub_console_key_ok(''), 'clé de liaison vérifiée');
check(count(hub_apps_local()) === count(hub_apps()), 'avant liaison : toutes les applications sont gérées ici');
hq("UPDATE apps SET console_url = 'https://centriva.test/console.php' WHERE slug = 'centriva'");
check(hdb()->query("SELECT console_url FROM apps WHERE slug = 'centriva'")->fetchColumn() === 'https://centriva.test/console.php', 'application rattachée à sa console');
check((bool)array_filter(hall('PRAGMA table_info(clients)'), fn($c) => $c['name'] === 'console_slug'), 'clients : identifiant de l\'espace de la console');

$rm = function (string $d) use (&$rm): void {
    foreach (glob($d . '/*') ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    rmdir($d);
};
$rm($tmp);
echo "\n" . ($ko ? "\033[31m$ko échec(s)\033[0m, " : '') . "\033[32m$ok test(s) réussi(s)\033[0m\n";
exit($ko ? 1 : 0);
