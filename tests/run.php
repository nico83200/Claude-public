<?php
declare(strict_types=1);

/**
 * Tests automatiques des règles métier, sur une base SQLite temporaire.
 *
 *   php tests/run.php
 *
 * À lancer avant de fabriquer un paquet de mise à jour (tools/build-update.php).
 */
if (PHP_SAPI !== 'cli') {
    exit("À lancer en ligne de commande.\n");
}
$tmp = sys_get_temp_dir() . '/cmd-tests-' . getmypid();
@mkdir($tmp);
$db = "$tmp/test.sqlite";
file_put_contents("$tmp/config.php", "<?php return ['db' => ['driver' => 'sqlite', 'path' => " . var_export($db, true) . "], 'app_name' => 'Tests', 'timezone' => 'Europe/Paris', 'anthropic_api_key' => ''];");
putenv("CMD_CONFIG=$tmp/config.php");
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
$_SESSION = [];

// Initialisation de la base avant l'amorçage (qui suppose les tables existantes)
define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
$GLOBALS['config'] = require "$tmp/config.php";
require APP . '/db.php';
require APP . '/helpers.php';
require APP . '/schema.php';
schema_install();
require APP . '/auth.php';
require APP . '/domain.php';
require APP . '/search.php';
require APP . '/stock.php';
require APP . '/notify.php';
require APP . '/updater.php';
require APP . '/features.php';
require APP . '/pdf.php';
require APP . '/cron.php';
define('APP_VERSION', trim((string)file_get_contents(ROOT . '/VERSION')));
require APP . '/install_demo.php';

$pass = 0;
$fail = 0;
function check(bool $cond, string $label): void
{
    global $pass, $fail;
    $cond ? $pass++ : $fail++;
    echo ($cond ? "  \033[32m✔\033[0m " : "  \033[31m✘\033[0m ") . $label . "\n";
}
function section(string $t): void
{
    echo "\n\033[1m$t\033[0m\n";
}
function as_user(string $email): array
{
    $u = one('SELECT * FROM users WHERE email = ?', [$email]);
    $_SESSION['uid'] = (int)$u['id'];
    return $u;
}

$adminId = insert('users', ['email' => 'admin@test.fr', 'password_hash' => password_hash('admin1234', PASSWORD_DEFAULT), 'first_name' => 'Ada', 'last_name' => 'Admin',
    'role' => 'admin', 'status' => 'active', 'created_at' => now()]);
install_base_data();
$_SESSION['uid'] = $adminId;
install_demo_data($adminId);
$c1 = (int)val("SELECT id FROM centers WHERE code = 'TIL'");
$c2 = (int)val("SELECT id FROM centers WHERE code = 'PORT'");
$pid = fn(string $ref) => (int)val('SELECT id FROM products WHERE reference = ?', [$ref]);

section('Installation et schéma');
check((int)val('SELECT COUNT(*) FROM products') > 40, 'catalogue de démonstration chargé');
check(column_exists('purchase_orders', 'invoice_status') && column_exists('products', 'compare_group'), 'colonnes v1.3 présentes');
schema_migrate();
check(true, 'migration rejouable sans erreur (idempotente)');

section('Recherche');
$prods = catalog_products($c1);
$byId = array_column($prods, null, 'id');
$top = fn(string $q) => ($k = array_key_first(search_local($prods, $q))) ? $byId[$k]['name'] : '';
check(str_contains($top('gans nitril'), 'Gants'), 'tolérance aux fautes : « gans nitril »');
check(str_contains($top('fievre'), 'Thermomètre'), 'synonymes : « fièvre » → thermomètre');
check(str_contains($top((string)val('SELECT barcode FROM products WHERE reference = ?', ['GN-M'])), 'taille M'), 'recherche par code-barres');
check(!isset(array_column(catalog_products($c2), null, 'reference')['TAPE-5']), 'fournisseur restreint invisible dans un autre centre');

section('Panier, validation par le responsable, bon de commande, réception, stock');
$claire = as_user('claire.secretaire@demo.fr');
cart_add((int)$claire['id'], $c1, $pid('THERM-IR'), 6);   // 6 × 29,90 € > seuil de 150 €
$req = cart_submit((int)$claire['id'], $c1, 'test', false);
check(val('SELECT approval_status FROM requests WHERE id = ?', [$req]) === 'pending', 'demande au-dessus du seuil → à valider par le responsable');
check((int)val("SELECT COUNT(*) FROM request_lines WHERE request_id = ? AND status = 'awaiting'", [$req]) === 1, 'lignes en attente de validation (hors circuit achats)');
as_user('sophie.responsable@demo.fr');
check(in_array($c1, approval_center_ids(), true) && approvals_pending_count() >= 1, 'le responsable voit la demande à valider');
q("UPDATE request_lines SET status = 'pending', qty = 4 WHERE request_id = ?", [$req]);
update('requests', ['approval_status' => 'approved'], 'id = ?', [$req]);
as_user('admin@test.fr');
$small = as_user('claire.secretaire@demo.fr');
cart_add((int)$small['id'], $c1, $pid('ABL-100'), 1);
$req2 = cart_submit((int)$small['id'], $c1, '', false);
check(val('SELECT approval_status FROM requests WHERE id = ?', [$req2]) === null, 'petite demande → directement au service achats');
as_user('admin@test.fr');
$lines = array_column(all("SELECT id FROM request_lines WHERE request_id IN (?, ?) AND supplier_id = ?", [$req, $req2, (int)val('SELECT supplier_id FROM products WHERE id = ?', [$pid('THERM-IR')])]), 'id');
$po = po_create($c1, (int)val('SELECT supplier_id FROM products WHERE id = ?', [$pid('THERM-IR')]), $lines);
check(po_totals($po)['lines'] === 2, 'bon de commande créé à partir des demandes');
update('purchase_orders', ['status' => 'commande', 'ordered_at' => now()], 'id = ?', [$po]);
$poRow = one('SELECT po.*, s.name AS supplier_name FROM purchase_orders po JOIN suppliers s ON s.id = po.supplier_id WHERE po.id = ?', [$po]);
$stockBefore = (int)(stock_row($c1, $pid('THERM-IR'))['qty'] ?? 0);
foreach (all('SELECT * FROM purchase_order_lines WHERE purchase_order_id = ?', [$po]) as $l) {
    $rec = (int)$l['product_id'] === $pid('THERM-IR') ? 2 : (int)$l['qty'];
    update('purchase_order_lines', ['qty_received' => $rec], 'id = ?', [$l['id']]);
    stock_from_reception($poRow, $l, 0, $rec);
}
check(po_refresh_reception_status($po) === 'partiel', 'réception partielle → statut « Reçu partiellement »');
check((int)stock_row($c1, $pid('THERM-IR'))['qty'] === $stockBefore + 2, 'le stock augmente des quantités reçues');
stock_move($c1, $pid('THERM-IR'), -1, 'sortie', 'test');
stock_count($c1, $pid('THERM-IR'), 10, 'inventaire test');
check((int)stock_row($c1, $pid('THERM-IR'))['qty'] === 10, 'inventaire : le stock compté remplace le stock théorique');

section('Factures');
$po2 = one('SELECT * FROM purchase_orders WHERE id = ?', [$po]);
$expected = invoice_check($po2)['expected'];
check(invoice_check(array_merge($po2, ['invoice_amount' => $expected]))['status'] === 'ok', 'facture égale au reçu → conforme');
check(invoice_check(array_merge($po2, ['invoice_amount' => $expected + 25]))['status'] === 'ecart', 'facture supérieure au reçu → écart détecté');

section('Budgets');
$b = budget_status($c1);
check($b['defined'] && $b['amount'] == 1500.0 && $b['spent'] > 0, 'budget du centre calculé (commandé, engagé, reste)');

section('Prix, comparateur et bascule vers le moins cher');
$gnm = one('SELECT * FROM products WHERE reference = ?', ['GN-M']);
$eq = cheaper_equivalent($gnm, $c1);
check($eq !== null && $eq['reference'] === 'HPS-GNM', 'équivalent moins cher trouvé chez un autre fournisseur');
check(count(recent_price_increases()) >= 1, 'hausse de prix détectée dans l\'historique');
$before = (int)val('SELECT COUNT(*) FROM price_history WHERE product_id = ?', [$gnm['id']]);
price_record((int)$gnm['id'], (float)$gnm['catalog_price'], (float)$gnm['negotiated_price'], 'test');
check((int)val('SELECT COUNT(*) FROM price_history WHERE product_id = ?', [$gnm['id']]) === $before, 'pas de doublon d\'historique si le prix ne change pas');

section('Commande groupée multi-centres');
$medi = (int)val("SELECT id FROM suppliers WHERE name = 'MédiDistrib'");
$pending = array_column(all("SELECT id FROM request_lines WHERE status = 'pending' AND supplier_id = ?", [$medi]), 'id');
$centersInvolved = (int)val("SELECT COUNT(DISTINCT center_id) FROM request_lines WHERE status = 'pending' AND supplier_id = ?", [$medi]);
$ref = po_create_group($medi, $pending);
$pos = all('SELECT * FROM purchase_orders WHERE group_ref = ?', [$ref]);
check(count($pos) === $centersInvolved && $centersInvolved >= 2, 'un bon par centre sous une même référence de groupe');
check(count(array_unique(array_column($pos, 'center_id'))) === count($pos), 'chaque bon reste attaché à un seul centre');
check(count(array_filter($pos, fn($p) => (float)$p['shipping_fee'] > 0)) <= 1, 'frais de port portés au plus par un bon (franco sur le total)');

section('PDF du bon de commande');
$pdf = po_pdf(array_map('intval', array_column($pos, 'id')));
check(str_starts_with($pdf, '%PDF-1.4') && str_contains($pdf, 'startxref') && str_contains($pdf, '%%EOF'), 'PDF valide généré (commande groupée)');
check(strlen($pdf) > 1500, 'PDF non vide');

section('Listes types et réapprovisionnement');
$claire = as_user('claire.secretaire@demo.fr');
$kits = kits_for($claire, $c1);
check(count($kits) >= 2, 'listes partagées visibles par le salarié');
check(count(kit_items((int)$kits[0]['id'], $c1)) >= 3, 'contenu de la liste type');
q('DELETE FROM cart_items WHERE user_id = ?', [$claire['id']]);
check(count(reorder_suggestions((int)$claire['id'], $c1)) >= 1, 'stocks bas proposés au réapprovisionnement');

section('Sécurité de connexion et mot de passe oublié');
for ($i = 0; $i < 5; $i++) {
    login_record('cible@test.fr', false);
}
check(login_blocked('cible@test.fr'), 'compte bloqué après 5 échecs en 15 minutes');
check(!login_blocked('autre@test.fr'), 'un autre compte n\'est pas bloqué');
$token = password_reset_create($claire);
check(password_reset_find($token) !== null, 'lien de réinitialisation valide');
check(password_reset_find(str_repeat('a', 64)) === null, 'jeton inconnu refusé');
update('password_resets', ['expires_at' => date('Y-m-d H:i:s', time() - 10)], 'user_id = ?', [$claire['id']]);
check(password_reset_find($token) === null, 'jeton expiré refusé');

section('Notifications, file d\'e-mails et tâches planifiées');
set_setting('mail_enabled', '1');
set_setting('smtp_host', '127.0.0.1');
set_setting('smtp_port', '1');            // aucun serveur : l'envoi doit échouer proprement
set_setting('smtp_secure', 'none');
as_user('admin@test.fr');   // on ne se notifie pas soi-même : l'action vient de l'administrateur
notify([(int)$claire['id']], 'po_ordered', 'Test', 'Corps du message', url('requests'));
check((int)val('SELECT COUNT(*) FROM mail_queue WHERE sent_at IS NULL') >= 1, 'e-mail mis en file d\'attente');
mail_queue_process(5);
check((int)val('SELECT MAX(attempts) FROM mail_queue') >= 1 && val('SELECT last_error FROM mail_queue ORDER BY id DESC LIMIT 1') !== null, 'échec SMTP enregistré pour nouvelle tentative (sans planter)');
set_setting('mail_enabled', '0');
insert('deadlines', ['title' => 'Test rappel', 'deadline_at' => date('Y-m-d H:i:s', time() + 3600 * 5), 'center_id' => $c1, 'created_at' => now()]);
$before = (int)val("SELECT COUNT(*) FROM notifications WHERE type = 'deadline_reminder'");
cron_deadline_reminders();
check((int)val("SELECT COUNT(*) FROM notifications WHERE type = 'deadline_reminder'") > $before, 'rappel envoyé la veille d\'une date limite');
cron_deadline_reminders();
check((int)val("SELECT COUNT(*) FROM deadlines WHERE title = 'Test rappel' AND reminded_at IS NOT NULL") === 1, 'un seul rappel par date limite');
update('purchase_orders', ['ordered_at' => date('Y-m-d H:i:s', strtotime('-20 days')), 'late_notified_at' => null], 'id = ?', [$po]);
check(cron_late_deliveries() >= 1, 'livraison en retard signalée');

section('E-mails au cas par cas');
as_user('admin@test.fr');
set_setting('mail_enabled', '1');
set_setting('mailev_po_ordered', '0');
$mq = (int)val('SELECT COUNT(*) FROM mail_queue');
$nb = (int)val("SELECT COUNT(*) FROM notifications WHERE type = 'po_ordered'");
notify([(int)$claire['id']], 'po_ordered', 'Cas désactivé', '', '');
check((int)val('SELECT COUNT(*) FROM mail_queue') === $mq, 'e-mail désactivé pour ce cas : aucun envoi');
check((int)val("SELECT COUNT(*) FROM notifications WHERE type = 'po_ordered'") === $nb + 1, '… mais la notification dans l\'application est créée');
set_setting('mailev_po_ordered', '1');
notify([(int)$claire['id']], 'po_ordered', 'Cas activé', '', '');
check((int)val('SELECT COUNT(*) FROM mail_queue') === $mq + 1, 'e-mail activé pour ce cas : mis en file');
set_setting('mail_enabled', '0');
check(!mail_case_enabled('po_ordered') && !send_mail('x@test.fr', 'a', 'b'), 'interrupteur général coupé : aucun e-mail, quel que soit le cas');
set_setting('mail_enabled', '1');
set_setting('mailev_supplier_po', '0');
check(!mail_case_enabled('supplier_po') && mail_case_enabled('password_reset'), 'envoi aux fournisseurs désactivable indépendamment');
set_setting('mail_enabled', '0');

section('Clé API et secrets chiffrés');
$GLOBALS['config']['anthropic_api_key'] = 'sk-ant-depuis-config-0000000000000000';
$plain = 'sk-ant-api03-TEST-' . bin2hex(random_bytes(12));
$enc = encrypt_secret($plain);
check(str_starts_with($enc, 'enc:') && !str_contains($enc, $plain), 'secret chiffré (illisible en base)');
check(decrypt_secret($enc) === $plain, 'déchiffrement fidèle');
check(decrypt_secret('enc:' . base64_encode(str_repeat('x', 60))) === '', 'secret altéré rejeté');
check(decrypt_secret('ancien-mot-de-passe') === 'ancien-mot-de-passe', 'ancienne valeur non chiffrée toujours lisible');
set_setting('ai_api_key', $enc);
check(ai_key_info()['source'] === 'settings' && ai_api_key() === $plain, 'la clé des paramètres est prioritaire sur config.php');
check(mask_secret($plain) === 'sk-ant-…' . substr($plain, -4), 'clé affichée masquée');

section('Mises à jour et sauvegardes');
check(update_path_allowed('app/domain.php') && !update_path_allowed('../evil.php') && !update_path_allowed('config.php') && !update_path_allowed('uploads/products/x.php'), 'chemins dangereux refusés dans un paquet');
$bk = "$tmp/base.zip";
backup_db_only($bk, 'test');
check(is_file($bk) && filesize($bk) > 1000, 'sauvegarde de la base créée');
$count = (int)val('SELECT COUNT(*) FROM products');
q("DELETE FROM kit_items");
$zip = new ZipArchive();
$zip->open($bk);
file_put_contents("$tmp/dump.jsonl", (string)$zip->getFromName('__database.jsonl'));
$zip->close();
db_restore_from("$tmp/dump.jsonl");
check((int)val('SELECT COUNT(*) FROM products') === $count && (int)val('SELECT COUNT(*) FROM kit_items') > 0, 'restauration de la base fidèle');

section('Journal d\'audit');
audit('Test audit', 'test', 1, ['a' => 1]);
check((int)val("SELECT COUNT(*) FROM audit_log WHERE action = 'Test audit'") === 1, 'entrée d\'audit enregistrée');
check(audit_diff(['p' => '1.00'], ['p' => '1.5'], ['p' => 'Prix']) === 'Prix : 1.00 → 1.5', 'différences décrites lisiblement');

// Nettoyage
array_map('unlink', glob("$tmp/*"));
@rmdir($tmp);
echo "\n" . ($fail ? "\033[31m$fail échec(s)\033[0m, " : '') . "\033[32m$pass test(s) réussi(s)\033[0m\n";
exit($fail ? 1 : 0);
