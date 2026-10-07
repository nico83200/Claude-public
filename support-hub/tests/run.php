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

echo "Licences\n";
$key = hub_create_client('Client test');
$c = hone('SELECT * FROM clients WHERE key_hash = ?', [hash('sha256', $key)]);
check(hub_licence($c)['status'] === 'active' && !hub_licence($c)['ai'], 'nouveau client : licence active sans échéance, sans option IA');
hq('UPDATE clients SET paid_until = ?, ai_option = 1 WHERE id = ?', [date('Y-m-d', strtotime('+10 days')), $c['id']]);
$l = hub_licence(hone('SELECT * FROM clients WHERE id = ?', [$c['id']]));
check($l['status'] === 'active' && $l['ai'] && $l['days_left'] === 10, 'abonnement payé : actif, option IA, 10 jours restants');
hq('UPDATE clients SET paid_until = ? WHERE id = ?', [date('Y-m-d', strtotime('-1 day')), $c['id']]);
check(hub_licence(hone('SELECT * FROM clients WHERE id = ?', [$c['id']]))['status'] === 'expired', 'sans délai de grâce : expirée dès le lendemain de l\'échéance');
hset('grace_days', '15');
hq('UPDATE clients SET paid_until = ? WHERE id = ?', [date('Y-m-d', strtotime('-5 days')), $c['id']]);
check(hub_licence(hone('SELECT * FROM clients WHERE id = ?', [$c['id']]))['status'] === 'grace', 'délai de grâce de 15 jours réglé : échue depuis 5 jours → grâce');
hq('UPDATE clients SET paid_until = ? WHERE id = ?', [date('Y-m-d', strtotime('-20 days')), $c['id']]);
$l = hub_licence(hone('SELECT * FROM clients WHERE id = ?', [$c['id']]));
check($l['status'] === 'expired' && !$l['ai'], 'échu depuis 20 jours : expiré, option IA coupée');
hq("UPDATE clients SET paid_until = NULL, status = 'suspended' WHERE id = ?", [$c['id']]);
check(hub_licence(hone('SELECT * FROM clients WHERE id = ?', [$c['id']]))['status'] === 'suspended', 'suspension manuelle');
hq("UPDATE clients SET status = 'active' WHERE id = ?", [$c['id']]);

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

echo "FAQ partagée\n";
check(hub_keywords('Comment imprimer une étiquette pour l\'étagère ?') === 'imprimer etiquette etagere', 'mots-clés tirés de la question (sans accents ni mots vides)');
hq("INSERT INTO faq (app, question, answer, admin_only, active, created_at, updated_at) VALUES ('*', 'Question A', 'Réponse A', 1, 1, ?, ?), ('autre', 'Question B', 'Réponse B', 0, 1, ?, ?), ('approvia', 'Question C', 'Réponse C', 0, 0, ?, ?)", [hnow(), hnow(), hnow(), hnow(), hnow(), hnow()]);
$f = hub_faq_for('approvia');
check(count($f) === 1 && $f[0]['q'] === 'Question A' && $f[0]['admin'] === true, 'FAQ filtrée par application, questions inactives exclues');

echo "Versions\n";
$zipPath = $tmp . '/pkg.zip';
$z = new ZipArchive();
$z->open($zipPath, ZipArchive::CREATE);
$z->addFromString('version.json', json_encode(['version' => '2.3.4', 'notes' => 'Nouveautés']));
$z->addFromString('VERSION', '2.3.4');
$z->close();
check(hub_inspect_package($zipPath) === ['version' => '2.3.4', 'notes' => 'Nouveautés'], 'version et notes lues dans le paquet');
foreach (['1.9.0', '1.10.0', '1.2.0'] as $ver) {
    hq("INSERT INTO releases (app, version, notes, file, sha256, size, published, created_at) VALUES ('approvia', ?, '', 'x.zip', 'h', 1, 1, ?)", [$ver, hnow()]);
}
check(hub_latest_release('approvia')['version'] === '1.10.0', 'dernière version selon la numérotation (1.10.0 > 1.9.0)');

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

$rm = function (string $d) use (&$rm): void {
    foreach (glob($d . '/*') ?: [] as $f) {
        is_dir($f) ? $rm($f) : unlink($f);
    }
    rmdir($d);
};
$rm($tmp);
echo "\n" . ($ko ? "\033[31m$ko échec(s)\033[0m, " : '') . "\033[32m$ok test(s) réussi(s)\033[0m\n";
exit($ko ? 1 : 0);
